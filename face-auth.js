/**
 * Face Authentication System
 * Face ID風の顔認証機能
 */

class FaceAuth {
    constructor() {
        this.isEnrolled = false;
        this.faceData = null;
        this.video = null;
        this.canvas = null;
        this.ctx = null;
        this.stream = null;
    }

    /**
     * 顔認証システムを初期化
     */
    async init() {
        try {
            // カメラアクセスを要求
            this.stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: 640, 
                    height: 480,
                    facingMode: 'user'
                }, 
                audio: false 
            });

            // ビデオ要素を作成
            this.video = document.createElement('video');
            this.video.srcObject = this.stream;
            this.video.play();
            this.video.style.width = '320px';
            this.video.style.height = '240px';
            this.video.style.borderRadius = '8px';

            // キャンバス要素を作成
            this.canvas = document.createElement('canvas');
            this.canvas.width = 640;
            this.canvas.height = 480;
            this.ctx = this.canvas.getContext('2d');

            return true;
        } catch (error) {
            console.error('Face Auth initialization failed:', error);
            return false;
        }
    }

    /**
     * 顔認証UIを表示
     */
    showFaceAuthUI() {
        const container = document.createElement('div');
        container.id = 'face-auth-container';
        container.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        `;

        const modal = document.createElement('div');
        modal.style.cssText = `
            background: white;
            padding: 30px;
            border: 2px solid #125E96;
            text-align: center;
            max-width: 500px;
        `;

        const title = document.createElement('h2');
        title.textContent = '顔認証';
        title.style.cssText = 'margin-bottom: 20px; color: #125E96;';

        const videoContainer = document.createElement('div');
        videoContainer.style.cssText = 'margin: 20px 0;';

        const status = document.createElement('div');
        status.id = 'face-auth-status';
        status.textContent = '起動中...';
        status.style.cssText = 'margin: 15px 0; padding: 10px; background: white; border: 2px solid #125E96; color: #125E96;';

        const buttons = document.createElement('div');
        buttons.style.cssText = 'margin-top: 20px;';

        const cancelBtn = document.createElement('button');
        cancelBtn.textContent = 'キャンセル';
        cancelBtn.style.cssText = 'margin: 0 10px; padding: 10px 20px; background: white; color: #125E96; border: 2px solid #125E96; cursor: pointer;';
        cancelBtn.onclick = () => this.hideFaceAuthUI();

        const enrollBtn = document.createElement('button');
        enrollBtn.textContent = '登録';
        enrollBtn.style.cssText = 'margin: 0 10px; padding: 10px 20px; background: #125E96; color: white; border: none; cursor: pointer;';
        enrollBtn.onclick = () => this.enrollFace();

        buttons.appendChild(cancelBtn);
        buttons.appendChild(enrollBtn);

        modal.appendChild(title);
        modal.appendChild(videoContainer);
        modal.appendChild(status);
        modal.appendChild(buttons);

        container.appendChild(modal);
        document.body.appendChild(container);

        // ビデオを表示
        videoContainer.appendChild(this.video);

        // 顔検出を開始
        this.startFaceDetection();
    }

    /**
     * 顔認証UIを非表示
     */
    hideFaceAuthUI() {
        const container = document.getElementById('face-auth-container');
        if (container) {
            container.remove();
        }
        this.stopCamera();
    }

    /**
     * 顔検出を開始
     */
    startFaceDetection() {
        const status = document.getElementById('face-auth-status');
        if (!status) return;

        const detectFace = () => {
            if (this.video && this.video.readyState === 4) {
                this.ctx.drawImage(this.video, 0, 0, 640, 480);
                const imageData = this.ctx.getImageData(0, 0, 640, 480);
                
                // 簡易的な顔検出（実際の実装ではより高度なアルゴリズムを使用）
                const hasFace = this.detectFaceInImage(imageData);
                
                if (hasFace) {
                    status.textContent = '認証中...';
                    status.style.background = 'white';
                    status.style.color = '#125E96';
                    
                    // 顔認証を実行
                    this.authenticateFace();
                } else {
                    status.textContent = '顔を向けてください';
                    status.style.background = 'white';
                    status.style.color = '#125E96';
                }
            }
            requestAnimationFrame(detectFace);
        };

        detectFace();
    }

    /**
     * 画像内で顔を検出（簡易版）
     */
    detectFaceInImage(imageData) {
        // 実際の実装では、TensorFlow.jsやOpenCV.jsなどのライブラリを使用
        // ここでは簡易的な検出ロジックを実装
        const data = imageData.data;
        let faceScore = 0;
        
        // 簡易的な顔検出アルゴリズム（実際の実装ではより高度）
        for (let i = 0; i < data.length; i += 4) {
            const r = data[i];
            const g = data[i + 1];
            const b = data[i + 2];
            
            // 肌色の範囲を検出
            if (r > 95 && g > 40 && b > 20 && 
                Math.max(r, g, b) - Math.min(r, g, b) > 15 &&
                Math.abs(r - g) > 15 && r > g && r > b) {
                faceScore++;
            }
        }
        
        return faceScore > 10000; // 閾値
    }

    /**
     * 顔認証を実行
     */
    async authenticateFace() {
        const status = document.getElementById('face-auth-status');
        
        try {
            // 現在の顔データを取得
            this.ctx.drawImage(this.video, 0, 0, 640, 480);
            const currentFaceData = this.canvas.toDataURL('image/jpeg', 0.8);
            
            // サーバーに送信して認証
            const response = await fetch(LVA.ajax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'lva_face_auth',
                    nonce: LVA.nonce,
                    face_data: currentFaceData
                })
            });

            const result = await response.json();
            
            if (result.success) {
                status.textContent = '成功';
                status.style.background = 'white';
                status.style.color = '#125E96';
                
                // 録画完了フラグを設定
                window.lvaRecordingCompleted = true;
                if (window.setRecordingCompleted) {
                    window.setRecordingCompleted();
                }
                
                // ログイン処理を継続
                setTimeout(() => {
                    this.hideFaceAuthUI();
                    document.getElementById('loginform').submit();
                }, 1000);
            } else {
                status.textContent = '失敗';
                status.style.background = 'white';
                status.style.color = '#125E96';
            }
        } catch (error) {
            console.error('Face authentication failed:', error);
            status.textContent = 'エラー';
            status.style.background = 'white';
            status.style.color = '#125E96';
        }
    }

    /**
     * 顔を登録
     */
    async enrollFace() {
        const status = document.getElementById('face-auth-status');
        status.textContent = '登録中...';
        
        try {
            this.ctx.drawImage(this.video, 0, 0, 640, 480);
            const faceData = this.canvas.toDataURL('image/jpeg', 0.8);
            
            const response = await fetch(LVA.ajax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'lva_face_enroll',
                    nonce: LVA.nonce,
                    face_data: faceData
                })
            });

            const result = await response.json();
            
            if (result.success) {
                status.textContent = '完了';
                status.style.background = 'white';
                status.style.color = '#125E96';
                console.log('Face enrollment successful:', result.data);
            } else {
                status.textContent = '失敗: ' + (result.data || 'Unknown error');
                status.style.background = 'white';
                status.style.color = '#125E96';
                console.error('Face enrollment failed:', result);
            }
        } catch (error) {
            console.error('Face enrollment failed:', error);
            status.textContent = 'エラー';
            status.style.background = 'white';
            status.style.color = '#125E96';
        }
    }

    /**
     * カメラを停止
     */
    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
    }
}

// グローバルにFaceAuthクラスを公開
window.FaceAuth = FaceAuth;
