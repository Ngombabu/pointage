// SCAN/scanner.js
class QRScanner {
    constructor() {
        this.video = document.getElementById('video');
        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d', { willReadFrequently: true });
        this.scanning = false;
        this.scanInterval = null;
        this.history = [];
        
        this.init();
    }

    async init() {
        // Vérifier session
        const isValid = await this.checkSession();
        if (!isValid) return;
        
        // Initialiser les événements
        document.getElementById('startScanBtn').addEventListener('click', () => this.start());
        document.getElementById('stopScanBtn').addEventListener('click', () => this.stop());
        document.getElementById('logoutBtn').addEventListener('click', () => this.logout());
        
        // Charger jsQR
        await this.loadJsQR();
    }

    async checkSession() {
        try {
            const response = await fetch('api/check_session.php');
            const data = await response.json();
            
            if (!data.logged_in) {
                window.location.href = 'index.html';
                return false;
            }
            
            // Récupérer les infos de la session
            const statusResponse = await fetch('api.php?action=status');
            const statusData = await statusResponse.json();
            
            if (statusData.success) {
                document.getElementById('heureLimite').textContent = statusData.heure_pointage;
                document.getElementById('shopName').textContent = statusData.shop_nom || 'Shop inconnu';
                document.getElementById('userName').textContent = statusData.superviseur_nom || 'Superviseur';
                document.getElementById('userAvatar').textContent = (statusData.superviseur_nom?.charAt(0) || '👤').toUpperCase();
            }
            
            return true;
        } catch (error) {
            console.error('Erreur session:', error);
            window.location.href = 'index.html';
            return false;
        }
    }

    async loadJsQR() {
        return new Promise((resolve) => {
            const cdnUrls = [
                'https://cdnjs.cloudflare.com/ajax/libs/jsqr/1.4.0/jsQR.min.js',
                'https://unpkg.com/jsqr@1.4.0/dist/jsQR.min.js'
            ];

            function tryLoad(index) {
                if (index >= cdnUrls.length) {
                    console.warn('jsQR non chargé');
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = cdnUrls[index];
                script.onload = resolve;
                script.onerror = () => tryLoad(index + 1);
                document.head.appendChild(script);
            }

            tryLoad(0);
        });
    }

    async start() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: 'environment' } 
            });
            this.video.srcObject = stream;
            
            await new Promise((resolve) => {
                this.video.onloadedmetadata = () => {
                    this.video.play();
                    resolve();
                };
            });

            this.scanning = true;
            this.scanInterval = setInterval(() => this.scan(), 500);
            
            document.getElementById('scanningIndicator').style.display = 'flex';
            this.showAlert('Scanner activé', 'success');
        } catch (error) {
            console.error('Erreur caméra:', error);
            this.showAlert('Impossible d\'accéder à la caméra', 'error');
        }
    }

    stop() {
        if (this.video.srcObject) {
            this.video.srcObject.getTracks().forEach(track => track.stop());
        }
        this.scanning = false;
        if (this.scanInterval) {
            clearInterval(this.scanInterval);
        }
        document.getElementById('scanningIndicator').style.display = 'none';
    }

    scan() {
        if (!this.scanning || this.video.readyState !== this.video.HAVE_ENOUGH_DATA) {
            return;
        }

        this.canvas.width = this.video.videoWidth;
        this.canvas.height = this.video.videoHeight;
        this.ctx.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);

        const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        
        if (window.jsQR) {
            const code = window.jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "dontInvert"
            });

            if (code) {
                this.processQRCode(code.data);
            }
        }
    }

    async processQRCode(data) {
        this.scanning = false;
        
        try {
            const response = await fetch('api.php?action=scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ qr_data: data })
            });

            const result = await response.json();
            
            if (result.success) {
                this.addToHistory({
                    agent: `${result.agent.prenom} ${result.agent.nom}`,
                    time: new Date().toLocaleTimeString(),
                    status: result.statut,
                    message: result.message,
                    details: `Heure: ${result.heure_pointage} (requis: ${result.heure_requise})`
                });
                this.showAlert(result.message, 'success');
            } else {
                this.addToHistory({
                    agent: result.agent ? `${result.agent.prenom} ${result.agent.nom}` : 'Inconnu',
                    time: new Date().toLocaleTimeString(),
                    status: 'ERREUR',
                    message: result.message,
                    error: true
                });
                this.showAlert(result.message, 'error');
            }
        } catch (error) {
            console.error('Erreur scan:', error);
            this.showAlert('Erreur de communication', 'error');
        }

        setTimeout(() => {
            this.scanning = true;
        }, 1000);
    }

    addToHistory(item) {
        this.history.unshift(item);
        if (this.history.length > 20) {
            this.history.pop();
        }
        this.updateHistoryDisplay();
    }

    updateHistoryDisplay() {
        const historyList = document.getElementById('historyList');
        
        if (this.history.length === 0) {
            historyList.innerHTML = '<div style="text-align: center; color: #64748b; padding: 2rem;">Aucun scan pour le moment</div>';
            return;
        }

        historyList.innerHTML = this.history.map(item => `
            <div class="history-item ${item.error ? 'error' : (item.status === 'RETARD' ? 'warning' : 'success')}">
                <div class="history-header">
                    <span class="history-agent">${item.agent}</span>
                    <span class="history-time">${item.time}</span>
                </div>
                <div class="history-details">${item.message}</div>
                ${item.details ? `<div class="history-details">${item.details}</div>` : ''}
                <div>
                    <span class="history-status ${item.status === 'RETARD' ? 'status-retard' : 'status-ok'}">
                        ${item.status || 'OK'}
                    </span>
                </div>
            </div>
        `).join('');
    }

    showAlert(message, type) {
        const alert = document.createElement('div');
        alert.className = `alert ${type}`;
        alert.innerHTML = message;
        document.body.appendChild(alert);
        
        setTimeout(() => alert.remove(), 3000);
    }

    logout() {
        this.stop();
        fetch('api/logout.php')
            .finally(() => {
                window.location.href = 'index.html';
            });
    }
}

// Démarrer l'application
new QRScanner();