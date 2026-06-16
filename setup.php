<?php
session_start();
include(__DIR__ . '/functions.php');

// Προστασία: Αν το setup έχει ολοκληρωθεί, ανακατεύθυνση στην αρχική
if (file_exists(__DIR__ . '/setup.lock')) {
    header("Location: index.php");
    exit();
}

// Έλεγχος τοπικού υποδικτύου /24
function is_local_network() {
    $server_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $s = explode('.', $server_ip);
    $c = explode('.', $client_ip);
    // Προσαρμογή για περιβάλλοντα localhost ή /24
    if ($server_ip === '127.0.0.1' || $server_ip === '::1') return true; 
    return (isset($s[0], $c[0]) && $s[0] == $c[0] && $s[1] == $c[1] && $s[2] == $c[2]);
}

$is_local = is_local_network();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Λογική αποθήκευσης προσωρινών στοιχείων DB
if (isset($_POST['save_db'])) {
    $_SESSION['temp_db'] = [
        'host' => $_POST['host'],
        'username' => $_POST['user'],
        'password' => $_POST['pass'],
        'Database Name' => $_POST['dbname']
    ];
    header("Location: ?step=2"); exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Οδηγός Εγκατάστασης | e-Αίτηση</title>
    <link rel="icon" type="./images/favicon.ico" href="/images/favicon.ico">
    <link href="./css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="./css/main.css" rel="stylesheet">
    <style>
        .list-group-item-success { background-color: #d1e7dd !important; color: #0f5132 !important; border-color: #badbcc !important; font-weight: bold;}
    </style>
</head>
<body class="bg-light">

    <header class="sticky-top shadow-sm" style="background: linear-gradient(135deg, #0d6efd, #0dcaf0);">
        <div class="container-fluid pt-3 pb-2 px-4">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-white fw-bold fs-3" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.2);">
                    <i class="bi bi-gear-wide-connected me-2"></i> Οδηγός Εγκατάστασης e-Αίτηση
                </div>
                <span class="badge bg-white text-primary rounded-pill shadow-sm px-3 py-2 fs-6">
                    Setup Wizard
                </span>
            </div>
        </div>
    </header> 

    <div id="main_div" class="container mt-5 mb-5">
        <div class="row justify-content-center">
            
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-header bg-dark text-white py-3 rounded-top-4">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Πρόοδος</h6>
                    </div>
                    <div class="list-group list-group-flush rounded-bottom-4">
                        <div class="list-group-item py-3 <?php echo $is_local ? 'list-group-item-success' : 'text-danger fw-bold'; ?>">
                            <i class="bi bi-geo-alt-fill me-2"></i> 1. Έλεγχος Δικτύου
                        </div>
                        <div class="list-group-item py-3 <?php echo ($step >= 2) ? 'list-group-item-success' : 'text-muted'; ?>">
                            <i class="bi bi-database-fill me-2"></i> 2. Σύνδεση & Δομή
                        </div>
                        <div class="list-group-item py-3 <?php echo ($step == 3) ? 'list-group-item-success' : 'text-muted'; ?>">
                            <i class="bi bi-check-circle-fill me-2"></i> 3. Ολοκλήρωση
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8 col-lg-9">
                <div class="card shadow-lg border-0 rounded-4" style="min-height: 400px;">
                    <div class="card-body p-4 p-md-5">
                        
                        <?php if (!$is_local): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-shield-lock text-danger" style="font-size: 4rem;"></i>
                                <h3 class="mt-3 fw-bold text-danger">Πρόσβαση Απαγορεύεται</h3>
                                <p class="text-muted fs-5">Η εγκατάσταση επιτρέπεται αυστηρά μόνο από το τοπικό υποδίκτυο του Server.</p>
                            </div>

                        <?php elseif ($step == 1): ?>
                            <h4 class="fw-bold text-primary border-bottom pb-3 mb-4"><i class="bi bi-hdd-network me-2"></i>Βήμα 1: Σύνδεση με Βάση Δεδομένων</h4>
                            
                            <?php 
                            global $srv_conf;
                            $current_conn = @mysqli_connect(
                                $srv_conf['DataBase']['host'] ?? '', 
                                $srv_conf['DataBase']['username'] ?? '', 
                                $srv_conf['DataBase']['password'] ?? '', 
                                $srv_conf['DataBase']['Database Name'] ?? ''
                            );

                            if (!$current_conn) {
                                echo '<div class="alert alert-danger shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Τα τρέχοντα στοιχεία στο <strong>srv_conf</strong> είναι λανθασμένα.</div>';
                                echo '<div class="card bg-light border-0 shadow-inner p-4 mt-4 rounded-4">';
                                echo '<form method="POST">';
                                echo '<p class="text-muted fw-bold mb-3 small text-uppercase">Εισαγωγή Νέων Στοιχείων:</p>';
                                echo '<div class="form-floating mb-3"><input type="text" name="host" class="form-control" id="fHost" placeholder="Host" required><label for="fHost">Host (π.χ. localhost)</label></div>';
                                echo '<div class="form-floating mb-3"><input type="text" name="user" class="form-control" id="fUser" placeholder="User" required><label for="fUser">Username</label></div>';
                                echo '<div class="form-floating mb-3"><input type="password" name="pass" class="form-control" id="fPass" placeholder="Password"><label for="fPass">Password</label></div>';
                                echo '<div class="form-floating mb-4"><input type="text" name="dbname" class="form-control" id="fDb" placeholder="DB Name" required><label for="fDb">Database Name</label></div>';
                                echo '<div class="text-end"><button type="submit" name="save_db" class="btn btn-primary btn-lg shadow-sm px-4"><i class="bi bi-save me-2"></i>Έλεγχος Σύνδεσης</button></div>';
                                echo '</form></div>';
                            } else {
                                $_SESSION['temp_db'] = $srv_conf['DataBase'];
                                echo '<div class="alert alert-success shadow-sm border-0 py-4 text-center"><i class="bi bi-check-circle-fill fs-1 d-block mb-3"></i><h5 class="fw-bold">Επιτυχής Σύνδεση!</h5><p class="mb-0">Η σύνδεση με τα στοιχεία του srv_conf ολοκληρώθηκε χωρίς προβλήματα.</p></div>';
                                echo '<div class="text-center mt-4"><a href="?step=2" class="btn btn-success btn-lg shadow-sm px-5">Συνέχεια στο Βήμα 2 <i class="bi bi-arrow-right ms-2"></i></a></div>';
                                mysqli_close($current_conn);
                            }
                            ?>

                        <?php elseif ($step == 2): ?>
                            <h4 class="fw-bold text-primary border-bottom pb-3 mb-4"><i class="bi bi-diagram-3 me-2"></i>Βήμα 2: Έλεγχος Δομής Βάσης</h4>
                            <?php 
                            $temp = $_SESSION['temp_db'] ?? null;
                            $conn = $temp ? @mysqli_connect($temp['host'], $temp['username'], $temp['password'], $temp['Database Name']) : false;

                            if (!$conn) {
                                echo '<div class="alert alert-danger shadow-sm border-0"><i class="bi bi-x-circle-fill me-2"></i>Αποτυχία σύνδεσης.</div>';
                                echo '<div class="text-center mt-4"><a href="?step=1" class="btn btn-warning shadow-sm"><i class="bi bi-arrow-left me-2"></i>Επιστροφή στο Βήμα 1</a></div>';
                            } else {
                                $tables = mysqli_query($conn, "SHOW TABLES");
                                if (mysqli_num_rows($tables) == 0) {
                                    if (isset($_GET['action']) && $_GET['action'] == 'create') {
                                        $res = db_sync_schema($conn);
                                        global $srv_conf; $srv_conf['DataBase'] = $temp;
                                        save_configuration($srv_conf);
                                        echo '<div class="alert alert-success shadow-sm border-0 py-3 text-center"><i class="bi bi-info-circle-fill me-2"></i>'.$res.'<br>Τα στοιχεία αποθηκεύτηκαν στο srv_conf.</div>';
                                        echo '<div class="text-center mt-4"><a href="?step=3" class="btn btn-primary btn-lg shadow-sm px-5">Συνέχεια <i class="bi bi-arrow-right ms-2"></i></a></div>';
                                    } else {
                                        echo '<div class="alert alert-warning shadow-sm border-0 py-4 text-center"><i class="bi bi-exclamation-triangle-fill fs-1 text-warning d-block mb-3"></i><h5 class="fw-bold">Η βάση είναι κενή</h5><p>Δεν βρέθηκαν πίνακες. Απαιτείται η δημιουργία της δομής.</p></div>';
                                        echo '<div class="text-center mt-4"><a href="?step=2&action=create" class="btn btn-success btn-lg shadow-sm"><i class="bi bi-magic me-2"></i>Δημιουργία Δομής</a></div>';
                                    }
                                } else {
                                    $schema_file = __DIR__ . '/data/db_structure.json';
                                    $expected = array_keys(json_decode(file_get_contents($schema_file), true));
                                    $current = [];
                                    while ($row = mysqli_fetch_array($tables)) { $current[] = $row[0]; }
                                    
                                    if (empty(array_diff($expected, $current))) {
                                        global $srv_conf; $srv_conf['DataBase'] = $temp;
                                        save_configuration($srv_conf);
                                        echo '<div class="alert alert-success shadow-sm border-0 py-4 text-center"><i class="bi bi-check-all fs-1 d-block mb-3"></i><h5 class="fw-bold">Η δομή είναι σωστή!</h5><p class="mb-0">Τα στοιχεία έχουν αποθηκευτεί με ασφάλεια.</p></div>';
                                        echo '<div class="text-center mt-4"><a href="?step=3" class="btn btn-primary btn-lg shadow-sm px-5">Συνέχεια <i class="bi bi-arrow-right ms-2"></i></a></div>';
                                    } else {
                                        echo '<div class="alert alert-danger shadow-sm border-0 py-4 text-center"><i class="bi bi-x-octagon-fill fs-1 text-danger d-block mb-3"></i><h5 class="fw-bold">Σφάλμα Δομής</h5><p class="mb-0">Η δομή της βάσης δεν ταυτίζεται με τις απαιτήσεις του συστήματος.</p></div>';
                                        echo '<div class="text-center mt-4"><a href="?step=1" class="btn btn-warning shadow-sm"><i class="bi bi-arrow-left me-2"></i>Επιστροφή</a></div>';
                                    }
                                }
                            }
                            ?>

                        <?php elseif ($step == 3): ?>
                            <div class="text-center py-5">
                                <div class="mb-4">
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                                </div>
                                <h2 class="mb-3 fw-bold text-dark">Η Εγκατάσταση Ολοκληρώθηκε!</h2>
                                <p class="text-muted mb-5 fs-5">Το σύστημα e-Αίτηση ρυθμίστηκε επιτυχώς και είναι έτοιμο για χρήση. <br>Συνδεθείτε τοπικά με Α.Φ.Μ. 1001 και ΑΜΚΑ 1234</p>
                                <a href="index.php" class="btn btn-success btn-lg shadow-lg rounded-pill px-5 py-3 fw-bold">
                                    Είσοδος στην Εφαρμογή <i class="bi bi-box-arrow-in-right ms-2"></i>
                                </a>
                            </div>
                            <?php file_put_contents(__DIR__.'/setup.lock', 'locked'); ?>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </div>
    </div>

    <footer class="bg-white border-top text-center text-lg-start fixed-bottom shadow-lg" style="z-index: 1030;">
        <div class="container-fluid py-2 px-4">
            <div class="row align-items-center">
                <div class="col-12 text-center small text-muted">
                    © <?php echo date("Y"); ?> <strong class="text-dark">Δ.Δ.Ε. Φλώρινας</strong> | Σύστημα Ηλεκτρονικών Αιτήσεων
                </div>
            </div>
        </div>
    </footer>

</body>
</html>