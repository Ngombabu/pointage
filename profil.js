// profil.js

// Session Manager
const SessionManager = {
    get(key) {
        let value = localStorage.getItem(key);
        if (!value) value = sessionStorage.getItem(key);
        return value ? JSON.parse(value) : null;
    },
    set(key, value) {
        localStorage.setItem(key, JSON.stringify(value));
        sessionStorage.setItem(key, JSON.stringify(value));
    },
    clear() {
        localStorage.clear();
        sessionStorage.clear();
    }
};

// Variables globales
let currentUser = null;
let currentMonth = new Date().getMonth() + 1;
let currentYear = new Date().getFullYear();

// Initialisation
document.addEventListener('DOMContentLoaded', async () => {
    // Vérifier connexion
    currentUser = SessionManager.get('user');
    if (!currentUser) {
        window.location.replace('connexion.html');
        return;
    }

    // Charger les années dans les sélecteurs
    loadYears();
    
    // Définir le mois actuel
    setCurrentMonth();
    
    // Charger les informations de l'utilisateur
    loadUserInfo();
    
    // Initialiser les onglets
    initTabs();
    
    // Charger les données initiales
    await loadPresences();
    await loadRetards();
    await loadRetenues();
    
    // Initialiser les événements
    initEvents();
});

// Charger les années (5 ans en arrière et 2 ans en avant)
function loadYears() {
    const currentYear = new Date().getFullYear();
    const yearSelects = ['presenceYear', 'retardYear', 'retenueYear'];
    
    yearSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '';
            for (let year = currentYear - 5; year <= currentYear + 2; year++) {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                if (year === currentYear) option.selected = true;
                select.appendChild(option);
            }
        }
    });
}

// Définir le mois actuel
function setCurrentMonth() {
    const month = String(currentMonth).padStart(2, '0');
    document.getElementById('presenceMonth').value = month;
    document.getElementById('retardMonth').value = month;
    document.getElementById('retenueMonth').value = month;
}

// Charger les infos utilisateur
function loadUserInfo() {
    if (!currentUser) return;
    
    const fullName = [currentUser.nom, currentUser.postnom, currentUser.prenom].filter(Boolean).join(' ');
    
    // Informations
    document.getElementById('infoFullName').textContent = fullName || 'Non renseigné';
    document.getElementById('infoEmail').textContent = currentUser.email || '-';
    document.getElementById('infoPhone').textContent = currentUser.telephone || '-';
    document.getElementById('infoType').textContent = currentUser.type || 'Agent';
    
    // Formulaire d'édition
    document.getElementById('editNom').value = currentUser.nom || '';
    document.getElementById('editPostnom').value = currentUser.postnom || '';
    document.getElementById('editPrenom').value = currentUser.prenom || '';
    document.getElementById('editEmail').value = currentUser.email || '';
    document.getElementById('editPhone').value = currentUser.telephone || '';
    
    // Header
    const headerInfo = document.getElementById('userHeaderInfo');
    if (headerInfo) {
        headerInfo.innerHTML = `
            <div class="user-badge">
                <span class="avatar">${(fullName[0] || '👤').toUpperCase()}</span>
                <span class="name">${fullName || 'Agent'}</span>
            </div>
            <button class="btn-logout" id="logoutBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                </svg>
                Quitter
            </button>
        `;
    }
}

// Initialiser les onglets
function initTabs() {
    const tabs = document.querySelectorAll('.tab-btn');
    const contents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;
            
            // Désactiver tous les onglets
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Activer l'onglet sélectionné
            tab.classList.add('active');
            document.getElementById(`tab-${target}`).classList.add('active');
        });
    });
}

// Afficher une alerte
function showAlert(message, type) {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert-message alert-${type}`;
    alert.textContent = message;
    container.appendChild(alert);
    
    setTimeout(() => alert.remove(), 5000);
}

// Charger les présences
async function loadPresences() {
    const month = document.getElementById('presenceMonth').value;
    const year = document.getElementById('presenceYear').value;
    
    try {
        const response = await fetch(`API/profil/presences.php?user_id=${currentUser.id}&month=${month}&year=${year}`);
        const data = await response.json();
        
        if (data.success) {
            // Mettre à jour les stats
            document.getElementById('totalPresences').textContent = data.total || 0;
            document.getElementById('presencesPeriod').textContent = `pour ${getMonthName(month)} ${year}`;
            document.getElementById('joursTravailles').textContent = data.jours || 0;
            
            // Mettre à jour le tableau
            const tbody = document.getElementById('presencesTableBody');
            if (data.presences && data.presences.length > 0) {
                tbody.innerHTML = data.presences.map(p => `
                    <tr>
                        <td>${formatDate(p.date)}</td>
                        <td>${formatTime(p.date)}</td>
                        <td>${p.shop_nom || 'N/A'}</td>
                        <td>
                            <span class="badge ${p.est_retard ? 'badge-warning' : 'badge-success'}">
                                ${p.est_retard ? '⚠️ Retard' : '✅ OK'}
                            </span>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--gray-500);">
                            Aucune présence pour ce mois
                        </td>
                    </tr>
                `;
            }
        }
    } catch (error) {
        console.error('Erreur chargement présences:', error);
    }
}

// Charger les retards
async function loadRetards() {
    const month = document.getElementById('retardMonth').value;
    const year = document.getElementById('retardYear').value;
    
    try {
        const response = await fetch(`API/profil/retards.php?user_id=${currentUser.id}&month=${month}&year=${year}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalRetards').textContent = data.total || 0;
            document.getElementById('retardsPeriod').textContent = `pour ${getMonthName(month)} ${year}`;
            document.getElementById('totalMinutesRetard').textContent = data.minutes || 0;
            
            const tbody = document.getElementById('retardsTableBody');
            if (data.retards && data.retards.length > 0) {
                tbody.innerHTML = data.retards.map(r => `
                    <tr>
                        <td>${formatDate(r.temps)}</td>
                        <td>${formatTime(r.temps)}</td>
                        <td>${r.heure_limite || '07:00'}</td>
                        <td>
                            <span class="badge badge-warning">⚠️ Retard</span>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--gray-500);">
                            Aucun retard pour ce mois
                        </td>
                    </tr>
                `;
            }
        }
    } catch (error) {
        console.error('Erreur chargement retards:', error);
    }
}

// Charger les retenues
async function loadRetenues() {
    const month = document.getElementById('retenueMonth').value;
    const year = document.getElementById('retenueYear').value;
    
    try {
        const response = await fetch(`API/profil/retenues.php?user_id=${currentUser.id}&month=${month}&year=${year}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalRetenues').textContent = data.total || 0;
            document.getElementById('retenuesPeriod').textContent = `pour ${getMonthName(month)} ${year}`;
            document.getElementById('montantTotal').textContent = `${data.montant_total || 0} €`;
            
            const tbody = document.getElementById('retenuesTableBody');
            if (data.retenues && data.retenues.length > 0) {
                tbody.innerHTML = data.retenues.map(r => `
                    <tr>
                        <td>${formatDate(r.moi)}</td>
                        <td><strong>${r.montant} €</strong></td>
                        <td>Retard du ${formatDate(r.date_retard)}</td>
                        <td>
                            <span class="badge badge-danger">💰 Retenue</span>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--gray-500);">
                            Aucune retenue pour ce mois
                        </td>
                    </tr>
                `;
            }
        }
    } catch (error) {
        console.error('Erreur chargement retenues:', error);
    }
}

// Formater la date
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// Formater l'heure
function formatTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Obtenir le nom du mois
function getMonthName(monthNum) {
    const months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                   'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    return months[parseInt(monthNum) - 1];
}

// Sauvegarder le profil
async function saveProfile(formData) {
    try {
        const response = await fetch('API/profil/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: currentUser.id,
                nom: formData.get('nom'),
                postnom: formData.get('postnom'),
                prenom: formData.get('prenom'),
                email: formData.get('email'),
                telephone: formData.get('telephone'),
                current_password: formData.get('currentPassword'),
                new_password: formData.get('newPassword')
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mettre à jour la session
            currentUser = { ...currentUser, ...data.user };
            SessionManager.set('user', currentUser);
            
            showAlert('Profil mis à jour avec succès', 'success');
            loadUserInfo(); // Recharger les infos
        } else {
            showAlert(data.message || 'Erreur lors de la mise à jour', 'error');
        }
    } catch (error) {
        showAlert('Erreur de connexion', 'error');
    }
}

// Initialiser les événements
function initEvents() {
    // Boutons de chargement
    document.getElementById('loadPresencesBtn').addEventListener('click', loadPresences);
    document.getElementById('loadRetardsBtn').addEventListener('click', loadRetards);
    document.getElementById('loadRetenuesBtn').addEventListener('click', loadRetenues);
    
    // Formulaire d'édition
    document.getElementById('editProfileForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const newPassword = formData.get('newPassword');
        const confirmPassword = formData.get('confirmPassword');
        
        if (newPassword && newPassword !== confirmPassword) {
            showAlert('Les mots de passe ne correspondent pas', 'error');
            return;
        }
        
        const btn = document.getElementById('saveProfileBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="loading-spinner"></span> Enregistrement...';
        
        await saveProfile(formData);
        
        btn.disabled = false;
        btn.innerHTML = 'Enregistrer les modifications';
    });
    
    // Déconnexion
    document.addEventListener('click', (e) => {
        if (e.target.id === 'logoutBtn' || e.target.closest('#logoutBtn')) {
            SessionManager.clear();
            fetch('API/connexion/logout.php')
                .finally(() => {
                    window.location.replace('connexion.html');
                });
        }
    });
}