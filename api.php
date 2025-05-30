<?php
/**
 * API per Strength Training Tracker
 * File: api.php
 */

// Previeni l'output del codice sorgente
if (!isset($_SERVER['HTTP_HOST'])) {
    die('Accesso diretto non permesso');
}

// Configurazione
define('DATA_FILE', __DIR__ . '/data/user_data.json');
define('BACKUP_DIR', __DIR__ . '/data/backups/');
define('API_KEY', 'StrongPass2024Tracker'); // CAMBIA QUESTA SUBITO!

// Headers CORS
header('Access-Control-Allow-Origin: https://danielepiani.it');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

// Gestione richieste OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verifica API Key
function getHeaders() {
    $headers = array();
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) == 'HTTP_') {
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
    }
    return $headers;
}

$headers = getHeaders();
$providedKey = isset($headers['X-Api-Key']) ? $headers['X-Api-Key'] : (isset($_GET['api_key']) ? $_GET['api_key'] : '');

if ($providedKey !== API_KEY) {
    http_response_code(401);
    die(json_encode(['error' => 'API key non valida']));
}

// Crea directory se non esistono
if (!file_exists(dirname(DATA_FILE))) {
    mkdir(dirname(DATA_FILE), 0755, true);
}
if (!file_exists(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

// Funzione per leggere i dati
function loadData() {
    if (!file_exists(DATA_FILE)) {
        // Struttura iniziale vuota
        $initialData = array(
            'users' => array(
                'default' => array(
                    'lastUpdate' => date('Y-m-d H:i:s'),
                    'data' => array(
                        'Panca' => array(
                            'maxTests' => array(),
                            'sessions' => array(),
                            'notes' => '',
                            'complementary' => array(),
                            'videoData' => null
                        ),
                        'Stacco' => array(
                            'maxTests' => array(),
                            'sessions' => array(),
                            'notes' => '',
                            'complementary' => array(),
                            'videoData' => null
                        ),
                        'Squat' => array(
                            'maxTests' => array(),
                            'sessions' => array(),
                            'notes' => '',
                            'complementary' => array(),
                            'videoData' => null
                        ),
                        'Military Press' => array(
                            'maxTests' => array(),
                            'sessions' => array(),
                            'notes' => '',
                            'complementary' => array(),
                            'videoData' => null
                        )
                    )
                )
            )
        );
        file_put_contents(DATA_FILE, json_encode($initialData, JSON_PRETTY_PRINT));
        return $initialData;
    }
    
    $content = file_get_contents(DATA_FILE);
    return json_decode($content, true);
}

// Funzione per salvare i dati
function saveData($data) {
    // Backup automatico prima di sovrascrivere
    if (file_exists(DATA_FILE)) {
        $backupName = BACKUP_DIR . 'backup_' . date('Y-m-d_H-i-s') . '.json';
        copy(DATA_FILE, $backupName);
        
        // Mantieni solo gli ultimi 10 backup
        $backups = glob(BACKUP_DIR . '*.json');
        if (count($backups) > 10) {
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            // Elimina i backup più vecchi
            for ($i = 0; $i < count($backups) - 10; $i++) {
                unlink($backups[$i]);
            }
        }
    }
    
    // Salva i nuovi dati
    $result = file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
    return $result !== false;
}

// Router principale
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
$userId = isset($_GET['user']) ? $_GET['user'] : (isset($_POST['user']) ? $_POST['user'] : 'default');

try {
    switch ($action) {
        case 'load':
            // Carica dati utente
            $allData = loadData();
            if (isset($allData['users'][$userId])) {
                echo json_encode(array(
                    'success' => true,
                    'data' => $allData['users'][$userId]['data'],
                    'lastUpdate' => $allData['users'][$userId]['lastUpdate']
                ));
            } else {
                echo json_encode(array(
                    'success' => false,
                    'error' => 'Utente non trovato'
                ));
            }
            break;
            
        case 'save':
            // Salva dati utente
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['data'])) {
                throw new Exception('Dati non validi');
            }
            
            $allData = loadData();
            $allData['users'][$userId] = array(
                'lastUpdate' => date('Y-m-d H:i:s'),
                'data' => $input['data']
            );
            
            if (saveData($allData)) {
                echo json_encode(array(
                    'success' => true,
                    'message' => 'Dati salvati con successo',
                    'lastUpdate' => $allData['users'][$userId]['lastUpdate']
                ));
            } else {
                throw new Exception('Errore nel salvataggio');
            }
            break;
            
        case 'backup':
            // Scarica backup completo
            $allData = loadData();
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="strength-tracker-backup-' . date('Y-m-d') . '.json"');
            echo json_encode($allData, JSON_PRETTY_PRINT);
            exit();
            
        case 'stats':
            // Statistiche generali
            $allData = loadData();
            $stats = array();
            
            foreach ($allData['users'] as $user => $userData) {
                $totalSessions = 0;
                foreach ($userData['data'] as $exercise => $exerciseData) {
                    $totalSessions += count($exerciseData['sessions']);
                }
                $stats[$user] = array(
                    'totalSessions' => $totalSessions,
                    'lastUpdate' => $userData['lastUpdate']
                );
            }
            
            echo json_encode(array(
                'success' => true,
                'stats' => $stats
            ));
            break;
            
        case 'test':
            // Test per verificare che PHP funzioni
            echo json_encode(array(
                'success' => true,
                'message' => 'API funzionante',
                'php_version' => phpversion(),
                'timestamp' => date('Y-m-d H:i:s')
            ));
            break;
            
        default:
            http_response_code(400);
            echo json_encode(array(
                'success' => false,
                'error' => 'Azione non valida'
            ));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}

// Fine del file
exit();
?>