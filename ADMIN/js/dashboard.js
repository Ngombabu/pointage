// ADMIN/js/dashboard.js

let currentUser = null;
let currentAction = null;
let currentEditId = null;

// Initialisation
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Dashboard initialisé');
    await checkSession();
    await loadStats();
    await loadSuperviseurs();
    await loadAgents();
    await loadShops();
    await loadConfig();
    loadYears();
    
    initNavigation();
    initEvents();
});

// Vérifier session
async function checkSession() {
    try {
        const response = await fetch('api/check_session.php');
        if (!response.ok) {
            throw new Error('Erreur réseau');
        }
        const data = await response.json();
        
        if (!data.success) {
            window.location.href = 'login_super.html';
            return;
        }
        
        currentUser = data.user;
        
        // Mettre à jour l'interface
        document.getElementById('userName').textContent = currentUser.nom;
        document.getElementById('userAvatar').textContent = currentUser.nom.charAt(0).toUpperCase();
        
        if (currentUser.is_first) {
            document.getElementById('adminBadge').style.display = 'inline-block';
        }
        
        // Désactiver certaines actions si pas admin
        if (!currentUser.is_first) {
            disableNonAdminActions();
        }
    } catch (error) {
        console.error('Erreur checkSession:', error);
        showAlert('Erreur de connexion au serveur', 'error');
        setTimeout(() => {
            window.location.href = 'login_super.html';
        }, 2000);
    }
}

// Désactiver actions non admin
function disableNonAdminActions() {
    // Cacher les boutons de suppression/modification des superviseurs
    document.querySelectorAll('[data-admin-only]').forEach(el => {
        el.style.display = 'none';
    });
    
    // Désactiver les boutons de configuration
    const configButtons = document.querySelectorAll('#section-config .btn-edit');
    configButtons.forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.title = 'Réservé à l\'administrateur';
    });
}

// Charger les statistiques
async function loadStats() {
    try {
        const response = await fetch('api/stats.php');
        if (!response.ok) throw new Error('Erreur réseau');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalSuperviseurs').textContent = data.total_superviseurs || 0;
            document.getElementById('totalAgents').textContent = data.total_agents || 0;
            document.getElementById('totalShops').textContent = data.total_shops || 0;
            document.getElementById('presencesToday').textContent = data.presences_today || 0;
        }
    } catch (error) {
        console.error('Erreur stats:', error);
    }
}

// Charger les superviseurs
async function loadSuperviseurs() {
    try {
        const response = await fetch('api/superviseurs.php');
        if (!response.ok) throw new Error('Erreur réseau');
        const data = await response.json();
        
        if (data.success) {
            const tbody = document.getElementById('superviseursTableBody');
            if (!tbody) return;
            
            tbody.innerHTML = data.superviseurs.map(s => `
                <tr>
                    <td>${s.id}</td>
                    <td>${s.nom}</td>
                    <td>${s.prenom}</td>
                    <td>${s.email}</td>
                    <td>${s.telephone || '-'}</td>
                    <td>${s.id == currentUser?.id ? 'Vous' : (s.is_first ? 'Admin' : 'Superviseur')}</td>
                    <td class="action-btns">
                        ${currentUser?.is_first && s.id != currentUser.id ? `
                            <button class="btn-delete" onclick="deleteSuperviseur(${s.id})" data-admin-only>Supprimer</button>
                        ` : ''}
                    </td>
                </tr>
            `).join('');
        }
    } catch (error) {
        console.error('Erreur superviseurs:', error);
    }
}

// Charger les agents
async function loadAgents(search = '') {
    try {
        let url = 'api/agents.php';
        if (search) {
            url += `?search=${encodeURIComponent(search)}`;
        }
        
        const response = await fetch(url);
        if (!response.ok) throw new Error('Erreur réseau');
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Réponse non-JSON:', text.substring(0, 200));
            throw new Error('Réponse serveur invalide');
        }
        
        if (data.success) {
            // Tableau du dashboard (premiers 10)
            const tbody = document.getElementById('agentsTableBody');
            if (tbody) {
                tbody.innerHTML = data.agents.slice(0, 10).map(a => `
                    <tr>
                        <td>${a.id}</td>
                        <td>${a.nom} ${a.prenom || ''}</td>
                        <td>${a.email}</td>
                        <td>${a.telephone || '-'}</td>
                        <td class="action-btns">
                            <button class="btn-edit" onclick="editAgent(${a.id})" title="Modifier">✏️</button>
                            <button class="btn-delete" onclick="deleteAgent(${a.id})" title="Supprimer">🗑️</button>
                            <button class="btn-add" onclick="showAgentRetenues(${a.id})" title="Voir retenues">💰</button>
                        </td>
                    </tr>
                `).join('');
            }
            
            // Tableau complet des agents
            const fullTbody = document.getElementById('agentsFullTableBody');
            if (fullTbody) {
                fullTbody.innerHTML = data.agents.map(a => `
                    <tr>
                        <td>${a.id}</td>
                        <td>${a.nom}</td>
                        <td>${a.prenom || ''}</td>
                        <td>${a.email}</td>
                        <td>${a.telephone || '-'}</td>
                        <td>Superviseur #${a.id_superviseur}</td>
                        <td class="action-btns">
                            <button class="btn-edit" onclick="editAgent(${a.id})" title="Modifier">✏️</button>
                            <button class="btn-delete" onclick="deleteAgent(${a.id})" title="Supprimer">🗑️</button>
                            <button class="btn-add" onclick="showAgentRetenues(${a.id})" title="Voir retenues">💰</button>
                        </td>
                    </tr>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Erreur agents:', error);
        showAlert('Erreur lors du chargement des agents', 'error');
    }
}

// Charger les shops
async function loadShops() {
    try {
        const response = await fetch('api/shops.php');
        if (!response.ok) throw new Error('Erreur réseau');
        const data = await response.json();
        
        if (data.success) {
            const tbody = document.getElementById('shopsTableBody');
            if (!tbody) return;
            
            tbody.innerHTML = data.shops.map(s => `
                <tr>
                    <td>${s.id}</td>
                    <td>${s.nom}</td>
                    <td>${s.adresse}</td>
                    <td>Superviseur #${s.id_superviseur}</td>
                    <td class="action-btns">
                        <button class="btn-edit" onclick="editShop(${s.id})" title="Modifier">✏️</button>
                        <button class="btn-delete" onclick="deleteShop(${s.id})" title="Supprimer">🗑️</button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (error) {
        console.error('Erreur shops:', error);
    }
}

// Charger configuration
async function loadConfig() {
    try {
        const response = await fetch('api/config.php');
        if (!response.ok) throw new Error('Erreur réseau');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('configHeure').textContent = data.heure || '07:00';
            document.getElementById('configPenalite').textContent = (data.penalite || '2.50') + ' $';
        }
    } catch (error) {
        console.error('Erreur config:', error);
    }
}

// Charger les années pour les sélecteurs
function loadYears() {
    const yearSelects = ['presenceYear', 'retardYear', 'retenueYear'];
    const currentYear = new Date().getFullYear();
    
    yearSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        select.innerHTML = '';
        for (let year = currentYear - 2; year <= currentYear + 2; year++) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            if (year === currentYear) option.selected = true;
            select.appendChild(option);
        }
    });
}

// Charger retenues mensuelles
async function loadRetenuesMensuelles() {
    const mois = document.getElementById('retenueMois')?.value || '01';
    const annee = document.getElementById('retenueAnnee')?.value || new Date().getFullYear();
    
    try {
        showAlert('Chargement des retenues...', 'success');
        
        const response = await fetch(`api/retenues.php?mois=${mois}&annee=${annee}`);
        if (!response.ok) throw new Error('Erreur réseau');
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Réponse non-JSON:', text.substring(0, 200));
            throw new Error('Réponse serveur invalide');
        }
        
        if (data.success) {
            document.getElementById('totalRetenuesMois').textContent = data.total || 0;
            document.getElementById('montantTotalMois').textContent = (data.montant_total || '0') + ' $';
            
            const tbody = document.getElementById('retenuesTableBody');
            if (tbody) {
                if (data.retenues && data.retenues.length > 0) {
                    tbody.innerHTML = data.retenues.map(r => `
                        <tr>
                            <td>${r.agent_nom || 'Agent'}</td>
                            <td>${r.date_formatee || new Date(r.moi).toLocaleDateString('fr-FR')}</td>
                            <td><strong>${r.montant} $</strong></td>
                            <td>${r.motif || 'Retard automatique'}</td>
                            <td>${r.shop || '-'}</td>
                            <td class="action-btns">
                                <button class="btn-delete" onclick="deleteRetenue(${r.id})" title="Supprimer">🗑️</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Aucune retenue pour ce mois</td></tr>';
                }
            }
            
            // Afficher les stats par agent si disponibles
            if (data.stats_par_agent && data.stats_par_agent.length > 0) {
                showAgentStats(data.stats_par_agent);
            }
        } else {
            showAlert(data.message || 'Erreur de chargement', 'error');
        }
    } catch (error) {
        console.error('Erreur retenues:', error);
        showAlert('Erreur lors du chargement des retenues', 'error');
    }
}

// Afficher les statistiques par agent
function showAgentStats(stats) {
    const container = document.getElementById('agentStatsContainer');
    if (!container) {
        // Créer le conteneur s'il n'existe pas
        const statsDiv = document.createElement('div');
        statsDiv.id = 'agentStatsContainer';
        statsDiv.className = 'stats-grid';
        statsDiv.style.marginBottom = '2rem';
        
        const section = document.querySelector('#section-retenues .section-card');
        if (section) {
            section.insertBefore(statsDiv, section.querySelector('.table-container'));
        }
    }
    
    const statsContainer = document.getElementById('agentStatsContainer');
    if (statsContainer) {
        statsContainer.innerHTML = stats.map(s => `
            <div class="stat-card" onclick="filterByAgent(${s.agent_id})" style="cursor: pointer;">
                <div class="stat-label">${s.nom}</div>
                <div class="stat-value">${s.montant} $</div>
                <div class="stat-sub">${s.total} retenue(s)</div>
            </div>
        `).join('');
    }
}

// Filtrer par agent
function filterByAgent(agentId) {
    const mois = document.getElementById('retenueMois')?.value || '01';
    const annee = document.getElementById('retenueAnnee')?.value || new Date().getFullYear();
    
    fetch(`api/retenues.php?mois=${mois}&annee=${annee}&agent_id=${agentId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert(`Affichage des retenues pour l'agent`, 'success');
                loadRetenuesMensuelles(); // Recharger avec le filtre
            }
        })
        .catch(() => showAlert('Erreur de filtrage', 'error'));
}

// Rechercher agents
function searchAgents() {
    const search = document.getElementById('searchAgent')?.value || '';
    loadAgents(search);
}

// Initialiser navigation
function initNavigation() {
    const navBtns = document.querySelectorAll('.nav-btn');
    const sections = document.querySelectorAll('.admin-section');
    
    navBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const section = btn.dataset.section;
            
            navBtns.forEach(b => b.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));
            
            btn.classList.add('active');
            const targetSection = document.getElementById(`section-${section}`);
            if (targetSection) {
                targetSection.classList.add('active');
                
                // Recharger les données si nécessaire
                if (section === 'retenues') {
                    loadRetenuesMensuelles();
                }
            }
        });
    });
}

// Initialiser événements
function initEvents() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logout);
    }
    
    const loadRetenuesBtn = document.getElementById('loadRetenuesBtn');
    if (loadRetenuesBtn) {
        loadRetenuesBtn.addEventListener('click', loadRetenuesMensuelles);
    }
    
    const searchBtn = document.querySelector('#section-dashboard button');
    if (searchBtn) {
        searchBtn.addEventListener('click', searchAgents);
    }
    
    const searchInput = document.getElementById('searchAgent');
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchAgents();
            }
        });
    }
}

// Déconnexion
async function logout() {
    try {
        await fetch('api/logout.php');
        window.location.href = 'login_super.html';
    } catch (error) {
        console.error('Erreur déconnexion:', error);
        window.location.href = 'login_super.html';
    }
}

// Gestion modale
function openModal(type, id = null) {
    currentAction = type;
    currentEditId = id;
    
    const modal = document.getElementById('modal');
    const modalTitle = document.getElementById('modalTitle');
    const modalFields = document.getElementById('modalFields');
    
    if (!modal || !modalTitle || !modalFields) return;
    
    modalTitle.textContent = id ? 'Modifier' : 'Ajouter';
    
    let fields = '';
    let title = '';
    
    switch(type) {
        case 'superviseur':
            title = 'Superviseur';
            fields = `
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" id="inputNom" required>
                </div>
                <div class="form-group">
                    <label>Postnom</label>
                    <input type="text" id="inputPostnom">
                </div>
                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" id="inputPrenom" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="inputEmail" required>
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" id="inputTelephone">
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" id="inputPassword" ${id ? 'placeholder="Laisser vide pour ne pas changer"' : 'required'}>
                </div>
            `;
            break;
            
        case 'agent':
            title = 'Agent';
            fields = `
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" id="inputNom" required>
                </div>
                <div class="form-group">
                    <label>Postnom</label>
                    <input type="text" id="inputPostnom">
                </div>
                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" id="inputPrenom" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="inputEmail" required>
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" id="inputTelephone">
                </div>
                <div class="form-group">
                    <label>ID Superviseur</label>
                    <input type="number" id="inputSuperviseur" value="${currentUser?.id || 1}" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" id="inputPassword" ${id ? 'placeholder="Laisser vide pour ne pas changer"' : 'required'}>
                </div>
            `;
            break;
            
        case 'shop':
            title = 'Shop';
            fields = `
                <div class="form-group">
                    <label>Nom du shop</label>
                    <input type="text" id="inputNom" required>
                </div>
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" id="inputAdresse" required>
                </div>
                <div class="form-group">
                    <label>ID Superviseur</label>
                    <input type="number" id="inputSuperviseur" value="${currentUser?.id || 1}" required>
                </div>
            `;
            break;
            
        case 'retenue':
            title = 'Retenue manuelle';
            fields = `
                <div class="form-group">
                    <label>ID Agent</label>
                    <input type="number" id="inputAgentId" required>
                </div>
                <div class="form-group">
                    <label>Montant ($)</label>
                    <input type="number" step="0.01" id="inputMontant" required>
                </div>
                <div class="form-group">
                    <label>Motif</label>
                    <input type="text" id="inputMotif" placeholder="Retard manuel...">
                </div>
            `;
            break;
    }
    
    modalTitle.innerHTML = `${id ? '✏️ Modifier' : '➕ Ajouter'} ${title}`;
    modalFields.innerHTML = fields;
    modal.classList.add('active');
    
    if (id) {
        loadDataForEdit(type, id);
    }
}

// Fermer modale
function closeModal() {
    const modal = document.getElementById('modal');
    if (modal) {
        modal.classList.remove('active');
    }
    currentAction = null;
    currentEditId = null;
}

// Soumission formulaire
document.addEventListener('submit', async (e) => {
    if (e.target.id === 'modalForm') {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', currentAction);
        
        if (currentEditId) {
            formData.append('id', currentEditId);
        }
        
        // Récupérer les valeurs selon le type
        const inputs = document.querySelectorAll('#modalFields input');
        inputs.forEach(input => {
            const fieldName = input.id.replace('input', '').toLowerCase();
            formData.append(fieldName, input.value);
        });
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading-spinner"></span> Enregistrement...';
        
        try {
            const response = await fetch('api/crud.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showAlert('Opération réussie!', 'success');
                closeModal();
                // Recharger les données
                loadStats();
                loadSuperviseurs();
                loadAgents();
                loadShops();
                if (currentAction === 'retenue') {
                    loadRetenuesMensuelles();
                }
            } else {
                showAlert(data.message || 'Erreur', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            showAlert('Erreur de connexion au serveur', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
});

// Afficher alerte
function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert ${type}`;
    alert.textContent = message;
    document.body.appendChild(alert);
    
    setTimeout(() => alert.remove(), 3000);
}

// Fonctions d'édition
function editAgent(id) {
    openModal('agent', id);
}

function editShop(id) {
    openModal('shop', id);
}

function editSuperviseur(id) {
    openModal('superviseur', id);
}

// Fonctions de suppression
async function deleteSuperviseur(id) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce superviseur ?')) return;
    
    try {
        const response = await fetch('api/crud.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_superviseur&id=${id}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Superviseur supprimé', 'success');
            loadSuperviseurs();
            loadStats();
        } else {
            showAlert(data.message || 'Erreur', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion au serveur', 'error');
    }
}

async function deleteAgent(id) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cet agent ?')) return;
    
    try {
        const response = await fetch('api/crud.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_agent&id=${id}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Agent supprimé', 'success');
            loadAgents();
            loadStats();
        } else {
            showAlert(data.message || 'Erreur', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion au serveur', 'error');
    }
}

async function deleteShop(id) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce shop ?')) return;
    
    try {
        const response = await fetch('api/crud.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_shop&id=${id}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Shop supprimé', 'success');
            loadShops();
            loadStats();
        } else {
            showAlert(data.message || 'Erreur', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion au serveur', 'error');
    }
}

async function deleteRetenue(id) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette retenue ?')) return;
    
    try {
        const response = await fetch('api/crud.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_retenue&id=${id}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Retenue supprimée', 'success');
            loadRetenuesMensuelles();
        } else {
            showAlert(data.message || 'Erreur', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion au serveur', 'error');
    }
}

// Éditer configuration
function editConfig(type) {
    if (!currentUser?.is_first) {
        showAlert('Seul l\'administrateur peut modifier la configuration', 'error');
        return;
    }
    
    const currentValue = type === 'heure' 
        ? document.getElementById('configHeure').textContent 
        : document.getElementById('configPenalite').textContent.replace(' $', '');
    
    const newValue = prompt(
        `Nouvelle valeur pour ${type === 'heure' ? 'l\'heure (HH:MM)' : 'la pénalité ($)'}:`, 
        currentValue
    );
    
    if (!newValue) return;
    
    fetch('api/update_config.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `type=${type}&value=${encodeURIComponent(newValue)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAlert('Configuration mise à jour', 'success');
            loadConfig();
        } else {
            showAlert(data.message || 'Erreur', 'error');
        }
    })
    .catch(() => showAlert('Erreur de connexion au serveur', 'error'));
}

// Voir les retenues d'un agent
async function showAgentRetenues(agentId) {
    try {
        showAlert('Chargement des données...', 'success');
        
        const response = await fetch(`api/get_agent_retenues.php?agent_id=${agentId}`);
        if (!response.ok) {
            throw new Error('Erreur réseau');
        }
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Réponse non-JSON:', text.substring(0, 200));
            throw new Error('Réponse serveur invalide');
        }
        
        if (data.success) {
            // Formater les détails
            const detailsHtml = data.details && data.details.length > 0 
                ? data.details.map(r => `
                    <tr>
                        <td>${r.date_formatee}</td>
                        <td><strong>${r.montant} $</strong></td>
                        <td>${r.motif}</td>
                        <td>${r.date_retard || '-'}</td>
                    </tr>
                `).join('')
                : '<tr><td colspan="4" style="text-align: center;">Aucune retenue</td></tr>';

            // Formater les stats mensuelles
            const mensuelHtml = data.mensuel && data.mensuel.length > 0
                ? data.mensuel.map(m => `
                    <tr>
                        <td>${m.mois}</td>
                        <td>${m.nombre}</td>
                        <td><strong>${m.total} $</strong></td>
                    </tr>
                `).join('')
                : '<tr><td colspan="3" style="text-align: center;">Aucune donnée mensuelle</td></tr>';

            const modalHtml = `
                <div class="modal active" id="agentModal" style="display: flex; z-index: 2000;">
                    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
                        <span class="modal-close" onclick="document.getElementById('agentModal').remove()">&times;</span>
                        <h3 class="modal-title">Retenues de ${data.agent.prenom} ${data.agent.nom}</h3>
                        
                        <div style="background: #e6f0ff; padding: 1.5rem; border-radius: 12px; margin: 1rem 0;">
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                                <div>
                                    <div style="color: #64748b; font-size: 0.9rem;">Total</div>
                                    <div style="font-size: 1.5rem; font-weight: 600; color: #2563eb;">${data.stats.total_global} $</div>
                                </div>
                                <div>
                                    <div style="color: #64748b; font-size: 0.9rem;">Nombre</div>
                                    <div style="font-size: 1.5rem; font-weight: 600;">${data.stats.nombre_total}</div>
                                </div>
                                <div>
                                    <div style="color: #64748b; font-size: 0.9rem;">Moyenne</div>
                                    <div style="font-size: 1.5rem; font-weight: 600;">${data.stats.moyenne} $</div>
                                </div>
                            </div>
                        </div>

                        <h4 style="margin: 1.5rem 0 1rem;">📊 Résumé mensuel</h4>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Mois</th>
                                        <th>Nombre</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${mensuelHtml}
                                </tbody>
                            </table>
                        </div>

                        <h4 style="margin: 1.5rem 0 1rem;">📋 Détail des retenues</h4>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Motif</th>
                                        <th>Date retard</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${detailsHtml}
                                </tbody>
                            </table>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button class="btn-save" onclick="document.getElementById('agentModal').remove()" style="flex: 1;">Fermer</button>
                            <button class="btn-delete" onclick="openModal('retenue', null, ${agentId})" style="flex: 1;">➕ Ajouter retenue</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        } else {
            showAlert(data.message || 'Erreur lors du chargement', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion au serveur', 'error');
    }
}

// Charger les données pour édition (à implémenter si besoin)
async function loadDataForEdit(type, id) {
    try {
        let endpoint = '';
        switch(type) {
            case 'agent':
                endpoint = 'agents.php';
                break;
            case 'shop':
                endpoint = 'shops.php';
                break;
            case 'superviseur':
                endpoint = 'superviseurs.php';
                break;
            default:
                return;
        }
        
        const response = await fetch(`api/${endpoint}`);
        const data = await response.json();
        
        if (data.success) {
            let item = null;
            if (type === 'agent') {
                item = data.agents.find(a => a.id == id);
            } else if (type === 'shop') {
                item = data.shops.find(s => s.id == id);
            } else if (type === 'superviseur') {
                item = data.superviseurs.find(s => s.id == id);
            }
            
            if (item) {
                // Remplir les champs
                Object.keys(item).forEach(key => {
                    const input = document.getElementById(`input${key.charAt(0).toUpperCase() + key.slice(1)}`);
                    if (input && item[key]) {
                        input.value = item[key];
                    }
                });
            }
        }
    } catch (error) {
        console.error('Erreur chargement données:', error);
    }
}

// Export des fonctions globales
window.openModal = openModal;
window.closeModal = closeModal;
window.editAgent = editAgent;
window.editShop = editShop;
window.editSuperviseur = editSuperviseur;
window.deleteSuperviseur = deleteSuperviseur;
window.deleteAgent = deleteAgent;
window.deleteShop = deleteShop;
window.deleteRetenue = deleteRetenue;
window.editConfig = editConfig;
window.showAgentRetenues = showAgentRetenues;
window.searchAgents = searchAgents;
window.loadRetenuesMensuelles = loadRetenuesMensuelles;
window.filterByAgent = filterByAgent;

// ================ SECTION CONNEXION ================

// Charger le statut de connexion
async function loadConnexionStatus() {
    try {
        const response = await fetch('api/connexion.php?action=get_status');
        const data = await response.json();
        
        if (data.success) {
            const status = data.status;
            const toggle = document.getElementById('connexionToggle');
            const label = document.getElementById('toggleLabel');
            const statusDisplay = document.getElementById('connexionStatus');
            const lastModified = document.getElementById('lastModified');
            
            statusDisplay.textContent = status;
            statusDisplay.style.color = status === 'ON' ? '#22c55e' : '#ef4444';
            
            toggle.checked = status === 'ON';
            label.textContent = status === 'ON' ? 'Système ouvert (ON)' : 'Système fermé (OFF)';
            
            if (data.last_modified_by) {
                lastModified.textContent = `Par ${data.last_modified_by}`;
            }
        }
    } catch (error) {
        console.error('Erreur chargement statut:', error);
    }
}

// Basculer le statut de connexion
async function toggleConnexion() {
    if (!currentUser?.is_first) {
        showAlert('Seul l\'administrateur peut modifier le statut de connexion', 'error');
        document.getElementById('connexionToggle').checked = !document.getElementById('connexionToggle').checked;
        return;
    }
    
    const toggle = document.getElementById('connexionToggle');
    const newStatus = toggle.checked ? 'ON' : 'OFF';
    
    try {
        const formData = new FormData();
        formData.append('status', newStatus);
        
        const response = await fetch('api/connexion.php?action=toggle', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(`Système ${newStatus === 'ON' ? 'ouvert' : 'fermé'}`, 'success');
            loadConnexionStatus();
        } else {
            showAlert(data.message || 'Erreur', 'error');
            toggle.checked = !toggle.checked;
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion', 'error');
        toggle.checked = !toggle.checked;
    }
}

// ================ SECTION RETARDS ================

// Charger les années pour les sélecteurs (ajouter à loadYears)
function loadYears() {
    const yearSelects = ['presenceYear', 'retardYear', 'retenueYear', 'retardAnnee'];
    const currentYear = new Date().getFullYear();
    
    yearSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        select.innerHTML = '';
        for (let year = currentYear - 2; year <= currentYear + 2; year++) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            if (year === currentYear) option.selected = true;
            select.appendChild(option);
        }
    });
}

// Charger les retards
async function loadRetards() {
    const mois = document.getElementById('retardMois')?.value || '01';
    const annee = document.getElementById('retardAnnee')?.value || new Date().getFullYear();
    
    try {
        showAlert('Chargement des retards...', 'success');
        
        const response = await fetch(`api/retards.php?mois=${mois}&annee=${annee}`);
        if (!response.ok) throw new Error('Erreur réseau');
        
        const data = await response.json();
        
        if (data.success) {
            // Mettre à jour les stats
            document.getElementById('totalRetards').textContent = data.stats.total_retards;
            document.getElementById('totalMinutesRetards').textContent = data.stats.total_minutes;
            document.getElementById('totalPenalitesRetards').textContent = data.stats.total_penalites + ' $';
            
            // Mettre à jour le tableau
            const tbody = document.getElementById('retardsTableBody');
            if (tbody) {
                if (data.retards && data.retards.length > 0) {
                    tbody.innerHTML = data.retards.map(r => `
                        <tr>
                            <td>${r.agent_nom}</td>
                            <td>${r.date_formatee}</td>
                            <td>${new Date(r.date).toLocaleTimeString('fr-FR')}</td>
                            <td>07:00</td>
                            <td><strong>${r.minutes_retard} min</strong></td>
                            <td>${r.shop}</td>
                            <td class="action-btns">
                                <button class="btn-view" onclick="showAgentRetenues(${r.id_agent})" title="Voir retenues">💰</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Aucun retard pour ce mois</td></tr>';
                }
            }
            
            // Afficher les stats par jour
            showDailyStats(data.retards);
        } else {
            showAlert(data.message || 'Erreur de chargement', 'error');
        }
    } catch (error) {
        console.error('Erreur retards:', error);
        showAlert('Erreur lors du chargement des retards', 'error');
    }
}

// Afficher les statistiques par jour
function showDailyStats(retards) {
    const container = document.getElementById('dailyStatsContainer');
    if (!container) {
        // Créer le conteneur
        const statsDiv = document.createElement('div');
        statsDiv.id = 'dailyStatsContainer';
        statsDiv.className = 'stats-grid';
        statsDiv.style.marginBottom = '2rem';
        
        const section = document.querySelector('#section-retards .section-card');
        if (section) {
            section.insertBefore(statsDiv, section.querySelector('.table-container'));
        }
    }
    
    const statsContainer = document.getElementById('dailyStatsContainer');
    if (!statsContainer || !retards) return;
    
    // Grouper par jour
    const daily = {};
    retards.forEach(r => {
        const day = new Date(r.date).toLocaleDateString('fr-FR');
        if (!daily[day]) {
            daily[day] = {
                date: day,
                count: 0,
                minutes: 0
            };
        }
        daily[day].count++;
        daily[day].minutes += r.minutes_retard;
    });
    
    const dailyArray = Object.values(daily).slice(0, 5); // 5 derniers jours
    
    statsContainer.innerHTML = dailyArray.map(d => `
        <div class="stat-card">
            <div class="stat-label">${d.date}</div>
            <div class="stat-value">${d.count}</div>
            <div class="stat-sub">${d.minutes} min de retard</div>
        </div>
    `).join('');
}

// Ajouter à initEvents
function initEvents() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logout);
    }
    
    const loadRetenuesBtn = document.getElementById('loadRetenuesBtn');
    if (loadRetenuesBtn) {
        loadRetenuesBtn.addEventListener('click', loadRetenuesMensuelles);
    }
    
    const searchBtn = document.querySelector('#section-dashboard button');
    if (searchBtn) {
        searchBtn.addEventListener('click', searchAgents);
    }
    
    const searchInput = document.getElementById('searchAgent');
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchAgents();
            }
        });
    }
    
    // Ajouter l'événement pour le bouton de chargement des retards
    const loadRetardsBtn = document.querySelector('#section-retards button');
    if (loadRetardsBtn) {
        loadRetardsBtn.addEventListener('click', loadRetards);
    }
}

// Appeler loadConnexionStatus après l'initialisation
// Ajouter cette ligne dans DOMContentLoaded
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Dashboard initialisé');
    await checkSession();
    await loadStats();
    await loadSuperviseurs();
    await loadAgents();
    await loadShops();
    await loadConfig();
    await loadConnexionStatus(); // Ajouter cette ligne
    loadYears();
    
    initNavigation();
    initEvents();
    
    // Charger les données par défaut pour les sections
    loadRetards();
    loadRetenuesMensuelles();
});

// Exporter les nouvelles fonctions
window.toggleConnexion = toggleConnexion;
window.loadRetards = loadRetards;


// ================ SECTION PRÉSENCES ================

// Variables globales pour les présences
let presenceChart = null;

// Charger les présences
async function loadPresences() {
    const mois = document.getElementById('presenceMois')?.value || '01';
    const annee = document.getElementById('presenceAnnee')?.value || new Date().getFullYear();
    const shopId = document.getElementById('presenceShop')?.value || 'all';
    const agentId = document.getElementById('presenceAgent')?.value || 'all';
    
    try {
        showAlert('Chargement des présences...', 'success');
        
        const url = `api/presences.php?mois=${mois}&annee=${annee}&shop_id=${shopId}&agent_id=${agentId}`;
        const response = await fetch(url);
        
        if (!response.ok) throw new Error('Erreur réseau');
        
        const data = await response.json();
        
        if (data.success) {
            updatePresenceStats(data.stats);
            updatePresenceChart(data.par_jour);
            updatePresenceParAgent(data.par_agent);
            updatePresenceDetails(data.details);
            updatePresenceFilters(data.filters);
        } else {
            showAlert(data.message || 'Erreur de chargement', 'error');
        }
    } catch (error) {
        console.error('Erreur présences:', error);
        showAlert('Erreur lors du chargement des présences', 'error');
    }
}

// Mettre à jour les statistiques
function updatePresenceStats(stats) {
    document.getElementById('totalPresences').textContent = stats.total || 0;
    document.getElementById('totalAgentsPresence').textContent = stats.total_agents || 0;
    document.getElementById('totalJours').textContent = stats.total_jours || 0;
    document.getElementById('moyenneParJour').textContent = stats.moyenne || 0;
}

// Mettre à jour le graphique
function updatePresenceChart(parJour) {
    const chartContainer = document.getElementById('presencesChart');
    if (!chartContainer) return;
    
    if (!parJour || parJour.length === 0) {
        chartContainer.innerHTML = '<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #64748b;">Aucune donnée</div>';
        return;
    }
    
    // Trier par date
    parJour.sort((a, b) => new Date(a.date) - new Date(b.date));
    
    // Trouver la valeur max pour l'échelle
    const maxValue = Math.max(...parJour.map(j => j.total));
    const chartHeight = 280;
    
    let chartHtml = '<div style="display: flex; align-items: flex-end; gap: 2px; height: 100%;">';
    
    parJour.forEach(jour => {
        const height = maxValue > 0 ? (jour.total / maxValue) * chartHeight : 0;
        chartHtml += `
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                <div style="width: 100%; background: linear-gradient(180deg, #2563eb, #7c3aed); height: ${height}px; border-radius: 4px 4px 0 0;" 
                     title="${jour.date_formatee}: ${jour.total} présence(s)"></div>
                <div style="font-size: 0.7rem; margin-top: 0.3rem; transform: rotate(-45deg);">${jour.date_formatee.slice(0,5)}</div>
            </div>
        `;
    });
    
    chartHtml += '</div>';
    chartContainer.innerHTML = chartHtml;
}

// Mettre à jour le tableau par agent
function updatePresenceParAgent(parAgent) {
    const tbody = document.getElementById('presencesParAgentBody');
    if (!tbody) return;
    
    if (!parAgent || parAgent.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Aucune donnée</td></tr>';
        return;
    }
    
    tbody.innerHTML = parAgent.map(a => `
        <tr>
            <td>${a.nom}</td>
            <td>${a.email}</td>
            <td><strong>${a.total}</strong></td>
            <td>${a.jours} jours</td>
            <td>
                <button class="btn-view" onclick="filterByAgent(${a.id})" style="padding: 0.3rem 1rem;">Voir</button>
                <button class="btn-view" onclick="showAgentRetenues(${a.id})" style="padding: 0.3rem 1rem;">💰 Retenues</button>
            </td>
        </tr>
    `).join('');
}

// Mettre à jour les détails des présences
function updatePresenceDetails(details) {
    const tbody = document.getElementById('presencesDetailBody');
    if (!tbody) return;
    
    if (!details || details.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Aucune présence</td></tr>';
        return;
    }
    
    tbody.innerHTML = details.map(d => `
        <tr>
            <td>${d.date_formatee}</td>
            <td>${d.agent}</td>
            <td>${d.shop}</td>
            <td>${new Date(d.date).toLocaleTimeString('fr-FR')}</td>
            <td>
                <span class="badge ${d.est_retard ? 'badge-warning' : 'badge-success'}">
                    ${d.est_retard ? '⚠️ Retard' : '✅ OK'}
                </span>
            </td>
        </tr>
    `).join('');
}

// Mettre à jour les filtres
function updatePresenceFilters(filters) {
    // Mettre à jour la liste des shops
    const shopSelect = document.getElementById('presenceShop');
    if (shopSelect && filters.shops) {
        shopSelect.innerHTML = '<option value="all">Tous les shops</option>';
        filters.shops.forEach(shop => {
            shopSelect.innerHTML += `<option value="${shop.id}">${shop.nom}</option>`;
        });
    }
    
    // Mettre à jour la liste des agents
    const agentSelect = document.getElementById('presenceAgent');
    if (agentSelect && filters.agents) {
        agentSelect.innerHTML = '<option value="all">Tous les agents</option>';
        filters.agents.forEach(agent => {
            agentSelect.innerHTML += `<option value="${agent.id}">${agent.nom} ${agent.prenom || ''}</option>`;
        });
    }
}

// Filtrer par agent
function filterByAgent(agentId) {
    const agentSelect = document.getElementById('presenceAgent');
    if (agentSelect) {
        agentSelect.value = agentId;
        loadPresences();
    }
}

// Exporter les années pour les présences
function loadPresenceYears() {
    const yearSelect = document.getElementById('presenceAnnee');
    if (!yearSelect) return;
    
    const currentYear = new Date().getFullYear();
    yearSelect.innerHTML = '';
    
    for (let year = currentYear - 2; year <= currentYear + 1; year++) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        if (year === currentYear) option.selected = true;
        yearSelect.appendChild(option);
    }
}

// Ajouter à l'initialisation
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Dashboard initialisé');
    await checkSession();
    await loadStats();
    await loadSuperviseurs();
    await loadAgents();
    await loadShops();
    await loadConfig();
    await loadConnexionStatus();
    loadYears();
    loadPresenceYears(); // Ajouter cette ligne
    
    initNavigation();
    initEvents();
    
    // Charger les données par défaut
    loadRetards();
    loadRetenuesMensuelles();
    loadPresences(); // Ajouter cette ligne
});

// Ajouter l'événement pour le bouton de chargement des présences
function initEvents() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logout);
    }
    
    const loadRetenuesBtn = document.getElementById('loadRetenuesBtn');
    if (loadRetenuesBtn) {
        loadRetenuesBtn.addEventListener('click', loadRetenuesMensuelles);
    }
    
    const searchBtn = document.querySelector('#section-dashboard button');
    if (searchBtn) {
        searchBtn.addEventListener('click', searchAgents);
    }
    
    const searchInput = document.getElementById('searchAgent');
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                searchAgents();
            }
        });
    }
    
    const loadRetardsBtn = document.querySelector('#section-retards button');
    if (loadRetardsBtn) {
        loadRetardsBtn.addEventListener('click', loadRetards);
    }
    
    // Ajouter l'événement pour le bouton de chargement des présences
    const loadPresencesBtn = document.querySelector('#section-presences button');
    if (loadPresencesBtn) {
        loadPresencesBtn.addEventListener('click', loadPresences);
    }
}

// Exporter la fonction
window.loadPresences = loadPresences;
window.filterByAgent = filterByAgent;