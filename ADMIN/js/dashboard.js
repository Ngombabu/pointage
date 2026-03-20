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
                            <button class="btn-edit" onclick="editAgent(${a.id})">✏️</button>
                            <button class="btn-delete" onclick="deleteAgent(${a.id})">🗑️</button>
                            <button class="btn-add" onclick="showAgentRetenues(${a.id})">💰</button>
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
                            <button class="btn-edit" onclick="editAgent(${a.id})">✏️</button>
                            <button class="btn-delete" onclick="deleteAgent(${a.id})">🗑️</button>
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
                        <button class="btn-edit" onclick="editShop(${s.id})">✏️</button>
                        <button class="btn-delete" onclick="deleteShop(${s.id})">🗑️</button>
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
            document.getElementById('configPenalite').textContent = (data.penalite || '2.50') + ' €';
        }
    } catch (error) {
        console.error('Erreur config:', error);
    }
}

// Charger les années
function loadYears() {
    const yearSelect = document.getElementById('retenueAnnee');
    if (!yearSelect) return;
    
    const currentYear = new Date().getFullYear();
    yearSelect.innerHTML = '';
    
    for (let year = currentYear - 2; year <= currentYear + 2; year++) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        if (year === currentYear) option.selected = true;
        yearSelect.appendChild(option);
    }
}

// Charger retenues mensuelles
async function loadRetenuesMensuelles() {
    const mois = document.getElementById('retenueMois')?.value || '01';
    const annee = document.getElementById('retenueAnnee')?.value || new Date().getFullYear();
    
    try {
        const response = await fetch(`api/retenues.php?mois=${mois}&annee=${annee}`);
        if (!response.ok) throw new Error('Erreur réseau');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalRetenuesMois').textContent = data.total || 0;
            document.getElementById('montantTotalMois').textContent = (data.montant_total || '0') + ' €';
            
            const tbody = document.getElementById('retenuesTableBody');
            if (tbody) {
                tbody.innerHTML = data.retenues.map(r => `
                    <tr>
                        <td>${r.agent_nom || 'Agent'} ${r.agent_prenom || ''}</td>
                        <td>${new Date(r.moi).toLocaleDateString('fr-FR')}</td>
                        <td>${r.montant} €</td>
                        <td>${r.motif || 'Retard automatique'}</td>
                        <td class="action-btns">
                            <button class="btn-delete" onclick="deleteRetenue(${r.id})">🗑️</button>
                        </td>
                    </tr>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Erreur retenues:', error);
    }
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
    
    switch(type) {
        case 'superviseur':
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
            fields = `
                <div class="form-group">
                    <label>ID Agent</label>
                    <input type="number" id="inputAgentId" required>
                </div>
                <div class="form-group">
                    <label>Montant (€)</label>
                    <input type="number" step="0.01" id="inputMontant" required>
                </div>
                <div class="form-group">
                    <label>Motif</label>
                    <input type="text" id="inputMotif" placeholder="Retard manuel...">
                </div>
            `;
            break;
    }
    
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
            showAlert('Erreur de connexion', 'error');
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
        showAlert('Erreur de connexion', 'error');
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
        showAlert('Erreur de connexion', 'error');
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
        showAlert('Erreur de connexion', 'error');
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
        showAlert('Erreur de connexion', 'error');
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
        : document.getElementById('configPenalite').textContent.replace(' €', '');
    
    const newValue = prompt(
        `Nouvelle valeur pour ${type === 'heure' ? 'l\'heure (HH:MM)' : 'la pénalité (€)'}:`, 
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
    .catch(() => showAlert('Erreur de connexion', 'error'));
}

// Voir les retenues d'un agent
async function showAgentRetenues(agentId) {
    try {
        const response = await fetch(`api/get_agent_retenues.php?agent_id=${agentId}`);
        const data = await response.json();
        
        if (data.success) {
            // Créer un modal pour afficher les détails
            const details = data.details.map(r => `
                <tr>
                    <td>${new Date(r.moi).toLocaleDateString('fr-FR')}</td>
                    <td>${r.montant} €</td>
                    <td>${r.motif || 'Retard'}</td>
                </tr>
            `).join('');
            
            const modalHtml = `
                <div class="modal active" id="agentModal">
                    <div class="modal-content">
                        <span class="modal-close" onclick="document.getElementById('agentModal').remove()">&times;</span>
                        <h3 class="modal-title">Retenues de ${data.agent.nom} ${data.agent.prenom}</h3>
                        <p><strong>Total: ${data.total_global} €</strong></p>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Motif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${details || '<tr><td colspan="3">Aucune retenue</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur lors du chargement', 'error');
    }
}

// Charger les données pour édition
async function loadDataForEdit(type, id) {
    // Cette fonction serait appelée pour pré-remplir le formulaire lors de l'édition
    console.log('Chargement des données pour', type, id);
}