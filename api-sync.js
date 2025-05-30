
// ===================================
// API Sync Module for Strength Tracker
// File: api-sync.js
// ===================================

// Configurazione API
const API_CONFIG = {
    url: 'https://danielepiani.it/tracker/api.php',
    key: 'StrongPass2024Tracker',
    user: 'default'
};

// Variabile per tracciare lo stato della connessione
let apiAvailable = false;

// Test connessione API
async function testAPI() {
    try {
        const response = await fetch(`${API_CONFIG.url}?action=test&api_key=${API_CONFIG.key}`);
        const result = await response.json();
        
        if (result.success) {
            console.log('✅ API connessa:', result.message);
            apiAvailable = true;
            return true;
        }
    } catch (error) {
        console.error('❌ API non disponibile:', error);
        apiAvailable = false;
    }
    return false;
}

// Carica dati dal server
async function loadFromServer() {
    if (!apiAvailable) return false;
    
    try {
        // Usa l'username dell'utente loggato se disponibile
        const user = (typeof currentUser !== 'undefined' && currentUser) ? currentUser.username : 'default';
        const response = await fetch(`${API_CONFIG.url}?action=load&user=${user}&api_key=${API_CONFIG.key}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            // Aggiorna i dati globali
            if (typeof data !== 'undefined') {
                data = result.data;
            }
            console.log('✅ Dati caricati dal server:', result.lastUpdate);
            
            // Aggiorna UI se la funzione esiste
            if (typeof updateUI === 'function') {
                updateUI();
            }
            
            // Mostra stato sincronizzazione
            showSyncStatus('Sincronizzato con il server', 'success');
            
            return true;
        }
    } catch (error) {
        console.error('❌ Errore caricamento dal server:', error);
        showSyncStatus('Modalità offline', 'warning');
    }
    
    return false;
}

// Salva dati sul server
async function saveToServer() {
    if (!apiAvailable) return false;
    
    try {
        const user = (typeof currentUser !== 'undefined' && currentUser) ? currentUser.username : 'default';
        const response = await fetch(API_CONFIG.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': API_CONFIG.key
            },
            body: JSON.stringify({
                action: 'save',
                user: user,
                data: data
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('✅ Salvato sul server:', result.lastUpdate);
            showSyncStatus('Salvato', 'success');
            return true;
        }
    } catch (error) {
        console.error('❌ Errore salvataggio sul server:', error);
        showSyncStatus('Errore sincronizzazione', 'error');
    }
    
    return false;
}

// Mostra stato sincronizzazione
function showSyncStatus(message, type = 'info') {
    const statusElement = document.getElementById('storage-status');
    if (!statusElement) return;
    
    const colors = {
        'success': '#10b981',
        'warning': '#f59e0b',
        'error': '#ef4444',
        'info': '#3b82f6'
    };
    
    statusElement.innerHTML = `<span style="color: ${colors[type]};">🔄 ${message}</span>`;
    statusElement.style.display = 'block';
    
    // Nascondi dopo 3 secondi per successo
    if (type === 'success') {
        setTimeout(() => {
            statusElement.style.display = 'none';
        }, 3000);
    }
}

// Funzione di sincronizzazione manuale
async function syncNow() {
    showSyncStatus('Sincronizzazione in corso...', 'info');
    
    if (!apiAvailable) {
        await testAPI();
    }
    
    if (apiAvailable) {
        // Prima salva i dati locali
        await saveToServer();
        
        // Poi ricarica dal server per essere sicuri
        await loadFromServer();
    } else {
        showSyncStatus('Server non raggiungibile', 'error');
    }
}

// Inizializza il modulo API
async function initializeAPI() {
    console.log('🔄 Inizializzazione modulo API...');
    
    // Attendi che le variabili globali siano disponibili
    if (typeof saveData === 'function') {
        // Sovrascrivi la funzione saveData per includere il salvataggio sul server
        const originalSaveData = saveData;
        window.saveData = function() {
            // Chiama la funzione originale
            originalSaveData();
            
            // Salva anche sul server
            if (apiAvailable) {
                saveToServer();
            }
        };
    }
    
    // Test connessione API
    await testAPI();
    
    // Se l'API è disponibile, carica i dati
    if (apiAvailable) {
        await loadFromServer();
    }
    
    // Aggiungi pulsante sync
    addSyncButton();
    
    // Setup auto-sync
    setupAutoSync();
}

// Aggiungi pulsante di sincronizzazione
function addSyncButton() {
    setTimeout(() => {
        const backupButtons = document.querySelector('.backup-buttons');
        if (backupButtons && !document.getElementById('sync-button')) {
            const syncButton = document.createElement('button');
            syncButton.id = 'sync-button';
            syncButton.className = 'btn btn-blue';
            syncButton.innerHTML = '🔄 Sincronizza';
            syncButton.onclick = syncNow;
            backupButtons.appendChild(syncButton);
        }
    }, 100);
}

// Setup sincronizzazione automatica
function setupAutoSync() {
    // Auto-sync ogni 5 minuti se online
    setInterval(() => {
        if (apiAvailable && navigator.onLine) {
            saveToServer();
        }
    }, 5 * 60 * 1000);
    
    // Ascolta eventi online/offline
    window.addEventListener('online', () => {
        console.log('✅ Tornato online');
        testAPI().then(() => {
            if (apiAvailable) syncNow();
        });
    });
    
    window.addEventListener('offline', () => {
        console.log('⚠️ Modalità offline');
        showSyncStatus('⚠️ Offline - I dati verranno sincronizzati al ritorno online', 'warning');
    });
}

// Avvia l'inizializzazione quando il DOM è pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAPI);
} else {
    // DOM già caricato
    setTimeout(initializeAPI, 100);
}

// Esporta le funzioni per uso globale
window.apiSync = {
    test: testAPI,
    load: loadFromServer,
    save: saveToServer,
    sync: syncNow,
    status: showSyncStatus
};