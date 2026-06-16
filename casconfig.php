<?php
/* ###########################################
 ΠΡΟΣΘΗΚΗ srv_conf για έλεγχο και καταχώριση global μεταβλητών
 */
function save_configuration4cas_old($conf_data=0)
{
    global $srv_conf;
    if($conf_data==0){$conf_data=$srv_conf;}
    
    $filename='./conf_data';
    $data2store= serialize($conf_data);
    $msg=file_put_contents($filename, $data2store,LOCK_EX);
    if($msg){return $msg.' Bytes Saved';}
        else{return $msg;}
}

function load_configuration4cas_old()
{
    $filename='./conf_data';
    if(!(file_exists($filename))){$filename=__DIR__.'/dbconf_data';}
    $conf_data= file_get_contents($filename);
    return unserialize($conf_data);    
}

function save_configuration4cas($conf_data = 0)
{
    global $srv_conf;
    if($conf_data === 0) { $conf_data = $srv_conf; }
    
    // Αλλαγή της διαδρομής στο νέο αρχείο
    $filename = __DIR__ . '/conf.json';
    
    // Χρήση json_encode αντί για serialize.
    // Το JSON_PRETTY_PRINT το κάνει ευανάγνωστο (με αλλαγές γραμμής)
    // Το JSON_UNESCAPED_UNICODE αποτρέπει τη μετατροπή των Ελληνικών σε \uXXXX
    $data2store = json_encode($conf_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    $msg = file_put_contents($filename, $data2store, LOCK_EX);
    
    // Το file_put_contents επιστρέφει false αν αποτύχει, οπότε αλλάζουμε λίγο τον έλεγχο
    if($msg !== false) {
        return $msg . ' Bytes Saved';
    } else {
        return false;
    }
}

function load_configuration4cas()
{
    $filename = __DIR__ . '/conf.json';
    
    // Μηχανισμός συμβατότητας (Fallback): 
    // Αν δεν έχει προλάβει να δημιουργηθεί το conf.json, προσπαθεί να διαβάσει το παλιό αρχείο
    if(!(file_exists($filename))) {
        $old_filename = __DIR__ . '/conf_data';
        if(file_exists($old_filename)) {
            $conf_data = file_get_contents($old_filename);
            return unserialize($conf_data);
        } else {
            // Αν δεν υπάρχει κανένα από τα δύο, επιστρέφει ένα άδειο array για να μην "σκάσει" ο κώδικας
            return [];
        }
    }
    
    $conf_data = file_get_contents($filename);
    
    // Το json_decode επιστρέφει από προεπιλογή Object. 
    // Προσθέτοντας το 'true' ως δεύτερη παράμετρο, το αναγκάζουμε να επιστρέψει Associative Array, 
    // ακριβώς όπως έκανε και το unserialize() πριν!
    return json_decode($conf_data, true);    
}


$srv_conf=load_configuration4cas();
/* ΑΡΧΙΚΟΠΟΙΗΣΗ ΜΕΤΑΒΛΗΤΩΝ CAS_configuration
$srv_conf['CAS Configuration']['cas_host']='sso.sch.gr';
$srv_conf['CAS Configuration']['client_service_name']='https://aitisi-dide.flo.sch.gr';
$srv_conf['CAS Configuration']['client_domain']='aitisi-dide.flo.sch.gr';
unset($srv_conf['CAS_configuration']['cas_host']);
unset($srv_conf['CAS_configuration']['client_service_name']);
unset($srv_conf['CAS_configuration']['client_domain']);
unset($srv_conf['CAS_configuration']);
save_configuration4cas();
*/
 


/**
 * The purpose of this central config file is configuring all examples
 * in one place with minimal work for your working environment
 * Just configure all the items in this config according to your environment
 * and rename the file to config.php
 *
 * PHP Version 7
 *
 * @file     config.php
 * @category Authentication
 * @package  PhpCAS
 * @author   Joachim Fritschi <jfritschi@freenet.de>
 * @author   Adam Franco <afranco@middlebury.edu>
 * @license  http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @link     https://wiki.jasig.org/display/CASC/phpCAS
 */

$phpcas_path = './addons/phpCAS/';

///////////////////////////////////////
// Basic Config of the phpCAS client //
///////////////////////////////////////

// Full Hostname of your CAS Server
//$cas_host = 'sso-01.sch.gr';
$cas_host = $srv_conf['CAS Configuration']['cas_host'];

// Context of the CAS Server
//$cas_context = '/cas';
$cas_context  = ''; // after go to pruduction sso

// Port of your CAS server. Normally for a https server it's 443
$cas_port = 443;

// Path to the ca chain that issued the cas server certificate
$cas_server_ca_cert_path = '/path/to/cachain.pem';

//////////////////////////////////////////
// Advanced Config for special purposes //
//////////////////////////////////////////

// The "real" hosts of clustered cas server that send SAML logout messages
// Assumes the cas server is load balanced across multiple hosts
$cas_real_hosts = array('cas-real-1.example.com', 'cas-real-2.example.com');

// Client config for the required domain name, should be protocol, hostname and port
//$client_service_name = 'https://aitisi-dide.flo.sch.gr';
$client_service_name=$srv_conf['CAS Configuration']['client_service_name'];

// Client config for cookie hardening
//$client_domain = 'aitisi-dide.flo.sch.gr';
$client_domain = $srv_conf['CAS Configuration']['client_domain'];
$client_path = 'phpcas';
$client_secure = true;
$client_httpOnly = true;
$client_lifetime = 0;

// Database config for PGT Storage
$db = 'pgsql:host=localhost;dbname=phpcas';
//$db = 'mysql:host=localhost;dbname=phpcas';
$db_user = 'phpcasuser';
$db_password = 'mysupersecretpass';
$db_table = 'phpcastabel';
$driver_options = '';

///////////////////////////////////////////
// End Configuration -- Don't edit below //
///////////////////////////////////////////

// Generating the URLS for the local cas example services for proxy testing
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    $curbase = 'https://' . $_SERVER['SERVER_NAME'];
} else {
    $curbase = 'http://' . $_SERVER['SERVER_NAME'];
}
if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
    $curbase .= ':' . $_SERVER['SERVER_PORT'];
}

$curdir = dirname($_SERVER['REQUEST_URI']) . "/";

// CAS client nodes for rebroadcasting pgtIou/pgtId and logoutRequest
$rebroadcast_node_1 = 'http://cas-client-1.example.com';
$rebroadcast_node_2 = 'http://cas-client-2.example.com';

// access to a single service
$serviceUrl = $curbase . $curdir . 'example_service.php';
// access to a second service
$serviceUrl2 = $curbase . $curdir . 'example_service_that_proxies.php';

$pgtBase = preg_quote(preg_replace('/^http:/', 'https:', $curbase . $curdir), '/');
$pgtUrlRegexp = '/^' . $pgtBase . '.*$/';

$cas_url = 'https://' . $cas_host;
if ($cas_port != '443') {
    $cas_url = $cas_url . ':' . $cas_port;
}
$cas_url = $cas_url . $cas_context;

// Set the session-name to be unique to the current script so that the client script
// doesn't share its session with a proxied script.
// This is just useful when running the example code, but not normally.
session_name(
    'session_for-'
    . preg_replace('/[^a-z0-9-]/i', '_', basename($_SERVER['SCRIPT_NAME']))
);
// Set an UTF-8 encoding header for internation characters (User attributes)
header('Content-Type: text/html; charset=utf-8');
?>