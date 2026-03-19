// SCAN/scanner.js

// Version locale de jsQR (incluse directement)
const jsQR = (function() {
    // Version simplifiée de jsQR pour éviter les problèmes CORS
    // En production, utilisez une copie locale du fichier
    
    // Fonction de détection QR code simplifiée
    function scanQR(imageData) {
        // Simulation pour le développement
        // En production, utilisez la vraie bibliothèque
        return null;
    }

    return scanQR;
})();

class QRScanner {
    constructor() {
        this.video = document.getElementById('video');
        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d');
        this.scanning = false;
        this.scanInterval = null;
        this.history = [];
        
        // Styles pour les boutons
        this.initStyles();
        this.init();
    }

    initStyles() {
        // Ajouter les styles manquants
        const style = document.createElement('style');
        style.textContent = `
            .btn-primary {
                background: #2563eb;
                color: white;
                border: none;
                padding: 1rem;
                border-radius: 15px;
                font-size: 1rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-primary:hover {
                background: #1d4ed8;
                transform: translateY(-2px);
            }
            .btn-secondary {
                background: transparent;
                color: #2563eb;
                border: 2px solid #2563eb;
                padding: 1rem;
                border-radius: 15px;
                font-size: 1rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-secondary:hover {
                background: #e6f0ff;
                transform: translateY(-2px);
            }
            .btn-primary:disabled, .btn-secondary:disabled {
                opacity: 0.5;
                cursor: not-allowed;
                transform: none;
            }
        `;
        document.head.appendChild(style);
    }

    async init() {
        // Vérifier session
        await this.checkSession();
        
        // Initialiser les événements
        document.getElementById('startScanBtn').addEventListener('click', () => this.start());
        document.getElementById('stopScanBtn').addEventListener('click', () => this.stop());
        document.getElementById('logoutBtn').addEventListener('click', () => this.logout());
    }

    async checkSession() {
        try {
            const response = await fetch('api.php?action=status');
            const data = await response.json();
            
            if (!data.success) {
                this.showAlert('Session expirée', 'error');
                setTimeout(() => {
                    window.location.href = '/POINTAGE/superviseur/';
                }, 2000);
                return;
            }

            // Mettre à jour l'interface
            document.getElementById('heureLimite').textContent = data.heure_pointage;
            document.getElementById('shopName').textContent = data.shop_nom || 'Shop inconnu';
        } catch (error) {
            console.error('Erreur session:', error);
            this.showAlert('Erreur de connexion', 'error');
        }
    }

    async start() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    facingMode: 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                } 
            });
            this.video.srcObject = stream;
            
            // Attendre que la vidéo soit prête
            await new Promise((resolve) => {
                this.video.onloadedmetadata = () => {
                    this.video.play();
                    resolve();
                };
            });

            this.scanning = true;
            
            // Démarrer le scan
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

        // Capturer une frame
        this.canvas.width = this.video.videoWidth;
        this.canvas.height = this.video.videoHeight;
        this.ctx.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);

        // Détecter QR code
        const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        
        // Utilisation de jsQR si disponible
        if (window.jsQR) {
            const code = window.jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: "dontInvert"
            });

            if (code) {
                this.processQRCode(code.data);
            }
        } else {
            // Fallback: simulation pour le développement
            console.log('jsQR non chargé');
        }
    }

    async processQRCode(data) {
        // Pause temporaire du scan
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

        // Reprendre le scan après 1 seconde
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
        fetch('../API/connexion/logout.php')
            .finally(() => {
                window.location.href = '/POINTAGE/superviseur/';
            });
    }
}

// Charger jsQR depuis un CDN alternatif
function loadJsQR() {
    return new Promise((resolve, reject) => {
        // Essayer plusieurs CDN
        const cdnUrls = [
            'https://cdnjs.cloudflare.com/ajax/libs/jsqr/1.4.0/jsQR.min.js',
            'https://unpkg.com/jsqr@1.4.0/dist/jsQR.min.js',
            'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js'
        ];

        function tryLoad(index) {
            if (index >= cdnUrls.length) {
                console.warn('jsQR non chargé, utilisation du fallback');
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

// Version locale de jsQR en cas d'échec
window.jsQR = window.jsQR || function(data, width, height, options) {
    // Version simplifiée pour le développement
    // En production, assurez-vous que jsQR est bien chargé
    return null;
};

// Démarrer l'application
loadJsQR().then(() => {
    new QRScanner();
}).catch(error => {
    console.error('Erreur chargement jsQR:', error);
    // Démarrer quand même avec le fallback
    new QRScanner();
});