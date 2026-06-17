<?php
include(__DIR__.'/classes/main_classes.php'); // main functions 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './addons/phpmailer/src/Exception.php';
require './addons/phpmailer/src/PHPMailer.php';
require './addons/phpmailer/src/SMTP.php';

// PHP EXCEL ADDON
require './addons/PHP2Spreadsheet/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// excel addon END

$srv_conf=load_configuration();

//db_export_schema(db_connect()); // Backup current db shema

/*
$srv_conf['DataBase']['username']='aitisimng';
$srv_conf['DataBase']['password']='TE@z[DzeRUl3Lg0x';
save_configuration();
*/
//$srv_conf['Main Users']['manager_password']='0';
//unset($srv_conf['secretary_password']);
//unset($srv_conf['session_timeout_manager']);
//unset($srv_conf['session_timeout_secretary']);
//$srv_conf['Main Configuration']['Apply to']='Δ.Δ.Ε. Φλώρινας';
//$srv_conf['Main Configuration']['Show local login']=1;
//unset($srv_conf['subnet']);
//$srv_conf['Main Configuration']['Subnet']=$srv_conf['subnet'];
//save_configuration();


// --- ΦΟΡΤΩΣΗ ΣΧΟΛΕΙΩΝ ΑΠΟ ΤΟ ΔΥΝΑΜΙΚΟ JSON ΑΡΧΕΙΟ ---
$schools_file = __DIR__ . '/data/schools_' . $srv_conf['Main Configuration']['dideid'] . '.json';

if (file_exists($schools_file)) {
    $json_data = file_get_contents($schools_file);
    $parsed_data = json_decode($json_data, true);
    
    // Εκχώρηση στους παγκόσμιους πίνακες που περιμένει το υπόλοιπο σύστημα
    $school = isset($parsed_data['schools']) ? $parsed_data['schools'] : array();
    
    // Προσοχή: Διατηρούμε το όνομα $exculde_schools (με το τυπογραφικό) 
    // γιατί έτσι το περιμένουν οι συναρτήσεις σου παρακάτω
    $exculde_schools = isset($parsed_data['exclude_schools']) ? $parsed_data['exclude_schools'] : array();
} else {
    // Fallback σε περίπτωση που το αρχείο δεν έχει δημιουργηθεί ακόμα
    $school = array();
    $exculde_schools = array();
}
// ---------------------------------------------------


    $user_application_filter[100]['enable']=0; // ΑΦΜ με δικαίωμα Δήλωσης Υπεραριθιμίες
    $user_application_filter[100]['afm']=array(); 
    $user_application_filter[101]['enable']=1; // ΑΦΜ με δικαίωμα Αίτησης Βελτίωσης και Οριστικής Τοποθέτησης Γενικής Παιδείας
    $user_application_filter[101]['afm']=array(
        '049498005',
        '028276561',
        '129080774'
    );
    $user_application_filter[102]['enable']=1; // ΑΦΜ με δικαίωμα Αίτησης Βελτίωσης και Οριστικής Τοποθέτησης Ειδικής Αγωγής
    $user_application_filter[102]['afm']=array(

        '028276561',
    );
// ###########################################
// ##########   DATABASE Connection ##########
// ###########################################
if(!check_if_database_not_exist())
{
    $con=mysqli_connect($srv_conf['DataBase']['host'],$srv_conf['DataBase']['username'],$srv_conf['DataBase']['password'],$srv_conf['DataBase']['Database Name']);
    dbq("SET NAMES utf8");
    mysqli_set_charset($con,"utf8");
}
// ###########################################
// ###########################################


$htmlout= new htmlout();

function save_configuration_old($conf_data=0)
{
    global $srv_conf;
    if($conf_data==0){$conf_data=$srv_conf;}
    
    $filename='./conf_data';
    $data2store= serialize($conf_data);
    $msg=file_put_contents($filename, $data2store,LOCK_EX);
    if($msg){return $msg.' Bytes Saved';}
        else{return $msg;}
}

function load_configuration_old()
{
    $filename='./conf_data';
    if(!(file_exists($filename))){$filename=__DIR__.'/dbconf_data';}
    $conf_data= file_get_contents($filename);
    return unserialize($conf_data);    
}

function save_configuration($conf_data = 0)
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

function load_configuration()
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

function show_array($arr) // print_r alternitive
{
    $retStr = '<ul style="text-align:left;">';
    if (is_array($arr)){
        foreach ($arr as $key=>$val){
            if (is_array($val)){
                $retStr .= '<li><span style="color:red;">' . $key . '</span><b> => </b><span style="color:blue;">' . show_array($val). '</span></li>';
            }else{
                $retStr .= '<li><span style="color:red;">' . $key . '</span><b> => </b><span style="color:blue;">' . $val . '</span></li>';
            }
        }
    }
    $retStr .= '</ul>';
    return $retStr;
}

function json2array($jkey,$jdata,$do_json_decode=1)
{
    $return_array=array();
    
    if($do_json_decode==1)
    {
        $key=json_decode($jkey);
        $data=json_decode($jdata);
    }
    else
    {
        $key=$jkey;
        $data=$jdata;
    }
    
    foreach($key as $aa=>$name)
    {
        $return_array[$name]=$data[$aa];
    }
    return $return_array;
}


function update_myslq_according2sch()
{
    global $con;
    session_start();
    $sql="SELECT AFM FROM USERS";
    $result=dbq($sql);
    $all_afms=array();
    while($row=mysqli_fetch_array($result))
    {
        $all_afms[]=$row['AFM'];
    }
    if(isset($_SESSION['sch_datas']['myschool']))
    {
       if($_SESSION['sch_datas']['myschool']==1)
       {
            $afm=mysqli_real_escape_string($con,$_SESSION['userdatas']['AFM']);
            $sname=mysqli_real_escape_string($con,$_SESSION['userdatas']['SNAME']);
            $fname=mysqli_real_escape_string($con,$_SESSION['userdatas']['FNAME']);
            $faname=mysqli_real_escape_string($con,$_SESSION['userdatas']['FANAME']);
            $am=mysqli_real_escape_string($con,$_SESSION['userdatas']['AM']);
            $organiki=mysqli_real_escape_string($con,$_SESSION['userdatas']['ORGANIKI']);
            $klados=mysqli_real_escape_string($con,$_SESSION['userdatas']['KLADOS']);
            $job=mysqli_real_escape_string($con,$_SESSION['userdatas']['JOB_STATUS']);
            $mobile=mysqli_real_escape_string($con,$_SESSION['userdatas']['MOBILE']);
            $email=mysqli_real_escape_string($con,$_SESSION['userdatas']['EMAIL']);
            if(in_array($afm,$all_afms))
            {
                $sql='UPDATE USERS SET SNAME="'.$sname.'",FNAME="'.$fname.'",FANAME="'.$faname.'",AM="'.$am.'",ORGANIKI="'.$organiki.'",KLADOS="'.$klados.'",JOB_STATUS="'.$job.'",MOBILE="'.$mobile.'",EMAIL="'.$email.'" WHERE AFM="'.$afm.'"';
            }
            else
            {
                $sql='INSERT INTO USERS (AFM,SNAME,FNAME,FANAME,AM,ORGANIKI,KLADOS,JOB_STATUS,MOBILE,EMAIL) VALUES ("'.$afm.'","'.$sname.'","'.$fname.'","'.$faname.'","'.$am.'","'.$organiki.'","'.$klados.'","'.$job.'","'.$mobile.'","'.$email.'")';
            }
            dbq($sql);
            logdata('Έγινε ενημέρωση της τοπικής Database για τον χρήστη με ΑΦΜ: '.$_SESSION['userdatas']['AFM'].' '.$_SESSION['userdatas']['SNAME'].' '.mb_substr($faname,0,3).' '.$_SESSION['userdatas']['FNAME']);
       }
       session_write_close();
    }


}

function main_page($secretary_auto_login=array(),$content='',$sch_usersdatas=array())
{
    
    global $htmlout,$srv_conf;
    session_start();
    session_destroy();

    $managerpass=date("dH");

    //show all sch_data
    $showme2debug_userdatas='';
    /*
    if(($sch_usersdatas!=0)&&(subnet_login()==1))
    {
        $showme2debug_userdatas='<hr>UsersDATAS<br>'.show_array($sch_usersdatas);
    }
    */



    $navbar='';
    $scripts='';
    $login_status='';

    if(check_if_database_not_exist())
    {
        $login_status='
        <div id="logon_box" class="container-xl p-5 my-5 bg-dark text-white">
            <section style="text-align:center; font-weight:bold; font-size:120%;">
                Παρουσιάστηκε Πρόβλημα στην Σύνδεση με την Βάση Δεδομένων <br> Παρακαλώ δοκιμάστε αργότερα.
            <section>
        </div
        ';
    }
    else
    {
        if(isset($secretary_auto_login['usertype']))
        {
            if($secretary_auto_login['usertype']=='secretary')
            {
                check_login('1002',$srv_conf['Main Users']['secretary_password']);
                $login_status='';
                $jcontent=show_managers_permanent_application($secretary_auto_login['AFM'],$secretary_auto_login['db_application_id']);
                
                // ΔΙΟΡΘΩΜΕΝΟ SCRIPT ΕΔΩ:
                $script='
                <script>
                    slide_nav_bar();
                    disable_appdata();
                    
                    // Ενεργοποίηση του Timer για τη Γραμματεία
                    timeout_session = document.getElementById("timeout_secretary").value;
                    startTimer(timeout_session, "countdown2disconnect");
                </script>
                ';

                $content=json_decode($jcontent,true)[0].$script;
                $navbar=$htmlout->navbar('Secretary');
            }
        }    
        else
        {
            if($sch_usersdatas!=array())
            {
                $afm=$sch_usersdatas['AFM'];
                check_login($afm,'',$sch_usersdatas);
                $login_status='';

                session_start();
                $sessions_datas=$_SESSION['userdatas'];
                $all_session=$_SESSION;
                
                session_write_close();
                $script='
                    <script>
                        slide_nav_bar();
                        timeout_session=document.getElementById("timeout_user").value;
                        startTimer(timeout_session,"countdown2disconnect");
                        change_sessionID('.json_encode($all_session,true).');
                        
                    </script>
                    ';


                    $father=mb_substr($sessions_datas['FANAME'],0,3);
                    $navbar=$htmlout->navbar($sessions_datas['SNAME'].' '.$father.' '.$sessions_datas['FNAME']);
                    $content=$script;

            }
            else
            {
                $login_status='
                <div id="logon_box" class="container d-flex justify-content-center mt-5 mb-5">
                    <div class="card shadow-lg border-0 rounded-4 w-100" style="max-width: 480px; background-color: #f8f9fa;">
                        <div class="card-body p-5 text-center">
                            <div class="mb-4">
                                <i class="bi bi-person-circle text-primary" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="mb-2 fw-bold text-primary">Καλώς Ήλθατε</h3>
                            <p class="text-muted mb-4">Επιλέξτε τρόπο σύνδεσης.</p>
                            
                            <div class="login-wrapper">'.$htmlout->login_table().'</div>
                        </div>
                    </div>
                </div>';
            }
        }
        
    }

    $html2return='
    <html lang="el">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex">
            <title>e-Αίτηση | Δ.Δ.Ε. Φλώρινας</title>
            <link rel="icon" type="./images/favicon.ico" href="/images/favicon.ico">
            <link href="./css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <link href="./css/main.css?'.time().'" rel="stylesheet">
            <script src="./js/bootstrap.bundle.min.js" ></script>
            <script src="./js/main.js?'.time().'" ></script>
            <script src="./js/imask.js"></script>
            <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
            
            <link rel="stylesheet" type="text/css" href="/addons/DataTables/datatables.min.css"/>
            <script type="text/javascript" src="/addons/DataTables/datatables.min.js"></script>
        </head>
        <body class="bg-light">

            <header class="sticky-top shadow-sm" style="background: linear-gradient(135deg, #0d6efd, #0dcaf0);">
                <div class="container-fluid pt-3 pb-2 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-white fw-bold fs-3" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.2);">
                            <i class="bi bi-building me-2"></i> '.$srv_conf['Header_and_Footer']['header'].'
                        </div>
                        <span class="badge bg-white text-primary rounded-pill shadow-sm px-3 py-2 fs-6">
                            Έκδοση '.$srv_conf['version'].'
                        </span>
                    </div>
                </div>
                
                <div id="slide_nav_menu" class="container-fluid slide-up pb-3 px-4">
                    <hr class="text-white opacity-25 my-2">
                    <div id="navbar" class="w-100">
                        '.$navbar.'
                    </div>
                </div>
            </header> 
            
            
            <div id="fade_div" class="overlay" style="display:none";></div>
            <div id="fade_div2" class="overlay2" style="display:none";></div>
                '.$login_status.'
            
            <div id="main_div" class="container-fluid bg-white shadow-sm py-4 mb-5" style="min-height: 70vh;">
                '.$content.' 
            </div>
            
            <div id="debug_userdatas" class="w-100">
                '.$showme2debug_userdatas.'
            </div>
            
            <footer class="bg-white border-top text-center text-lg-start fixed-bottom shadow-lg" style="z-index: 1030;">
                <div class="container-fluid py-2 px-4">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center text-md-start small text-muted">
                            <strong class="text-dark">'.$srv_conf['Header_and_Footer']['footer_left'].'</strong><br>
                            <a href="mailto:'.$srv_conf['Header_and_Footer']['footer_left_mail'].'" class="text-decoration-none text-secondary">
                                <i class="bi bi-envelope-fill me-1"></i>'.$srv_conf['Header_and_Footer']['footer_left_mail'].'
                            </a>
                        </div>
                        <div class="col-md-4 text-center small text-muted">
                            © '.date("Y").' 
                            <a class="text-primary fw-bold text-decoration-none ms-1" href="'.$srv_conf['Header_and_Footer']['footer_center_copyright_link'].'" target="_blank">
                                '.$srv_conf['Header_and_Footer']['footer_center_copyright'].'
                            </a>
                        </div>
                        <div class="col-md-4 text-center text-md-end text-danger fw-bold small" id="countdown2disconnect">
                            <img src="./images/bestviewed.jpg" height="25" class="ms-2 d-none d-md-inline" style="opacity: 0.7;">
                        </div>
                    </div>
                </div>
            </footer>
        </body>
    </html>
    ';
    return $html2return;

}

function afmed($number2afm) // return number as text 9 charachters
{
    return str_pad($number2afm,9,"0",STR_PAD_LEFT);
}



function afm_data($afm)
{
    $sql="SELECT * FROM USERS WHERE AFM=$afm";
    if($afm=='')
        {
            logdata('Something is wrong. afm data asked for >'.$afm.'<. NO afm found');
            $row['fullname']='NO ΑΦΜ found';
        }
    else
    {
        $result=dbq($sql);
        $row= mysqli_fetch_array($result);
        $row['fullname']=$row['SNAME'].' '.mb_substr($row['FANAME'],0,3).' '.$row['FNAME'];
    }
    
    

    return $row;
}

function disconnect() // depricated now using logout.php 
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // 1. Καταγραφή στα Logs
    if(isset($_SESSION['userdatas']['AFM']))
    {
        $userdatas = afm_data($_SESSION['userdatas']['AFM']);
        logdata('ΑΠΟΣΎΝΔΕΣΗ Χρήστη ' . $userdatas['fullname']);    
    }

    // 2. Πλήρης καταστροφή συνεδρίας
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    // 3. Απαντάμε "OK" στη JavaScript - ΧΩΡΙΣ REDIRECT
    echo "OK";
}


function check_login($afm,$amka,$users_data_from_myschool=0)
{
    global $htmlout,$srv_conf;
    $usersdatas=array();

    if($afm!='')
    {
        $sql="SELECT * FROM USERS WHERE AFM=$afm";
        $result=dbq($sql);
        /*
        while($row=mysqli_fetch_array($result))
        {   

        }
        */
        $usersdatas=mysqli_fetch_array($result);

        if (!empty($users_data_from_myschool) && is_array($users_data_from_myschool))
        {
            session_start();
            $_SESSION['sch_datas']=$users_data_from_myschool;
            $_SESSION['sch_datas']['timestamps'][]=time();
            $_SESSION['userdatas']['AFM']=$users_data_from_myschool['AFM'];
            $_SESSION['userdatas']['SNAME']=$users_data_from_myschool['SNAME'];
            $usersdatas['SNAME']=$users_data_from_myschool['SNAME'];
            $_SESSION['userdatas']['FNAME']=$users_data_from_myschool['FNAME'];
            $usersdatas['FNAME']=$users_data_from_myschool['FNAME'];
            $_SESSION['userdatas']['FANAME']=$users_data_from_myschool['FANAME'];
            $usersdatas['FANAME']=$users_data_from_myschool['FANAME'];
            $_SESSION['userdatas']['AM']=$users_data_from_myschool['AM'];
            $usersdatas['AM']=$users_data_from_myschool['AM'];
            $_SESSION['userdatas']['ORGANIKI']=$users_data_from_myschool['ORGANIKI'];
            $usersdatas['ORGANIKI']=$users_data_from_myschool['ORGANIKI'];
            $_SESSION['userdatas']['ORGANIKIbyID']=$users_data_from_myschool['ORGANIKIbyID'];
            $usersdatas['ORGANIKIbyID']=$users_data_from_myschool['ORGANIKIbyID'];
            $_SESSION['userdatas']['KLADOS']=$users_data_from_myschool['KLADOS'];
            $usersdatas['KLADOS']=$users_data_from_myschool['KLADOS'];
            $_SESSION['userdatas']['JOB_STATUS']=$users_data_from_myschool['JOB_STATUS'];
            $usersdatas['JOB_STATUS']=$users_data_from_myschool['JOB_STATUS'];
            $_SESSION['userdatas']['MOBILE']=$users_data_from_myschool['MOBILE'];
            $usersdatas['MOBILE']=$users_data_from_myschool['MOBILE'];
            $_SESSION['userdatas']['EMAIL']=$users_data_from_myschool['EMAIL'];
            $usersdatas['EMAIL']=$users_data_from_myschool['EMAIL'];
            session_write_close();
            update_myslq_according2sch();
        }


        if(($users_data_from_myschool!=0)&&(isset($usersdatas['AFM'])))
        {
            $amka=$usersdatas['AMKA'];
            session_start();
            $_SESSION['userdatas']=$usersdatas;
            $_SESSION['logontime']=time();
            $_SESSION['usertype']='user';

            session_write_close();
        }
    }

    $html2return=array();
    if(($afm==$usersdatas['AFM'])&&($amka==$usersdatas['AMKA'])&&(isset($usersdatas['AFM']))) // check if user
    {
        
        $father=mb_substr($usersdatas['FANAME'],0,3);
        $html2return=array(
            'access'=>1,
            'navbar'=>$htmlout->navbar($usersdatas['SNAME'].' '.$father.' '.$usersdatas['FNAME'],0),
            'html1'=>$usersdatas['SNAME'].' '.$father.' '.$usersdatas['FNAME'],
            'html2'=>'void',
        );
        session_start();
        if(($users_data_from_myschool==0))
        {
            logdata('Σύνδεση Χρήστη ΑΦΜ: '.$usersdatas['AFM'].' '.$html2return['html1']);
        }
        else
        {
            logdata('Σύνδεση Χρήστη ΑΦΜ: '.$usersdatas['AFM'].' '.$html2return['html1'].' από SCH.GR');
        }
        $_SESSION['userdatas']=$usersdatas;
        $_SESSION['logontime']=time();
        $_SESSION['usertype']='user';
        session_write_close();
    }
    else{
        $apass=$srv_conf['Main Users']['manager_password'];
        if($apass==0){$apass=date("dH");}
        if(($afm==1001)&&($amka==$apass)) //check manager
        {
            $html2return=array(
                'access'=>1,
                'navbar'=>$htmlout->navbar('Manager',0),
                'html1'=>'Manager',
                'html2'=>'void',
            );
            session_start();
            $_SESSION['logontime']=time();
            $_SESSION['usertype']='manager';
            if(($users_data_from_myschool==0))
            {
                logdata('Σύνδεση Χρήστη '.$html2return['html1']);
            }
            else
            {
                logdata('Σύνδεση Χρήστη '.$html2return['html1'].' απο SCH.GR');
            }
            session_write_close();
    
        }
        else
        {
            if(($afm==1002)&&($amka==$srv_conf['Main Users']['secretary_password'])&&(subnet_login()==1)) //check secretary
            {
                $html2return=array(
                    'access'=>1,
                    'navbar'=>$htmlout->navbar('Secretary',0),
                    'html1'=>'Secretary',
                    'html2'=>'void',
                );
                session_start();
                $_SESSION['logontime']=time();
                $_SESSION['usertype']='secretary';
                logdata('Σύνδεση Χρήστη '.$html2return['html1']);
                session_write_close();
        
            }
            else
            {
                if(($users_data_from_myschool==0))
                {
                    $html2return=array(
                        'access'=>0,
                        'html1'=>$usersdatas['AFM'],
                        'display_manage_button'=>0
                    );
                    logdata('Αποτυχία Σύνδεσης Χρήστη ΑΦΜ: '.$afm); 
                }
               
            }

        }


    }

    

    return json_encode($html2return,TRUE);
}



// ##################################################################################################
// ##################################################################################################
function dbq($sql,$id=0) // DataBaseQuery also show if errors and stops
{
global $con;
if (!($result=mysqli_query($con,$sql)))
  {
  echo mysqli_error($con).'<section style="color:red;font-weight:bold;"><br>
                    Database ERROR as reported above 
                    <br>
                    SQL query: '.$sql.'<br>
                    </section>
                ';
  die;
  }
  if($id==0){return $result;}else {return mysqli_insert_id($con);}
}

// ##################################################################################################
// ##################################################################################################
function sql_check($str)
{
    global $con;
    return mysqli_real_escape_string($con, $str);
    
}

// ##################################################################################################
// ##################################################################################################

function check_if_database_not_exist()
 // return 0 if exists, 1 if server cannot be reached, 2 if DB not exist
{
global $srv_conf;

mysqli_report(MYSQLI_REPORT_OFF);

$link = mysqli_connect($srv_conf['DataBase']['host'],$srv_conf['DataBase']['username'],$srv_conf['DataBase']['password']);
if (!$link) {
  return 1;
}

$db_selected = mysqli_select_db($link,$srv_conf['DataBase']['Database Name']);
if (!$db_selected) {
  return 2;
}
return 0;
}

// ##################################################################################################
// ##################################################################################################

function logdata($data)
{
    // get ip of client
    $ip=get_ip();

    $new_content=date("Y/m/d H:i:s").' IP: '.$ip.' '.$data;
    $log_file='./logs/log'.date("Ym").'.txt'; 
    $contents=file_get_contents($log_file);
    $rtrn=file_put_contents($log_file,$contents.$new_content."\r\n",LOCK_EX);
    return $rtrn;
    
}

function get_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else {
                    $ip = $_SERVER['REMOTE_ADDR'];
                    }
    return $ip;
}

function subnet_login()
{
    global $srv_conf;
    if(isset($srv_conf['Main Configuration']['Subnet']))
        {
            $mylan=explode('.',$srv_conf['Main Configuration']['Subnet']);
        }
        else{return 0;}
    $yourlan=explode('.',get_ip());
    if(($mylan[0]==$yourlan[0])&&($mylan[1]==$yourlan[1])&&($mylan[2]==$yourlan[2]))
    {return 1;}
    else{return 0;}

}


function show_firstlogon_page()
{
    session_start();
    $html2return='';
    if(($_SESSION['usertype']=='manager')||($_SESSION['usertype']=='secretary'))
        {$html2return=show_usersdata4manager();}
    
    if(isset($_SESSION['userdatas']))
        {$html2return=show_usersdata();}
    
    session_write_close();

    return $html2return;

}

function show_usersdata4manager()
{
    $html2return='
    <div class="container-fluid mt-4 mb-5">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2"></i>Αναζήτηση Χρηστών ανά Γράμμα</h4>
            </div>
            <div class="card-body p-4 text-center bg-light">
                '.carousel().'
            </div>
        </div>
        <div id="manager_list_names_by_letter" class="w-100"></div>
    </div>
    ';

    return $html2return;
}

function carousel()
{
    $html2return='<div class="d-flex flex-wrap justify-content-center gap-2">';
    $snames_first_letter=array();
    $sql="SELECT * FROM USERS";
    $result=dbq($sql);
    while($row=mysqli_fetch_array($result))
    {
        $snames_first_letter[mb_substr($row['SNAME'],0,1)]++;
    }

    ksort($snames_first_letter);
    foreach($snames_first_letter as $letter=>$hits)
    {
        $eng_flag='';
        // Αν είναι ξένος χαρακτήρας προσθέτουμε ένα χρώμα για να ξεχωρίζει
        if (!preg_match('/[^A-Za-z0-9]/', $letter)){$eng_flag='border-danger text-danger';}
        
        $html2return.='
        <button class="btn btn-outline-primary fw-bold shadow-sm '.$eng_flag.'" onclick="show_letter_list(\''.$letter.'\');" title="'.$hits.' Χρήστες">
            '.$letter.' <span class="badge bg-secondary ms-1 rounded-pill">'.$hits.'</span>
        </button>';
    }
    $html2return .= '</div>';
    
    return $html2return;
}

function show_usersdata4manager_depricated()
{
    $html2return='
    <div id="manager_carousel" class="container-fluid " style="text-align:center;">
    <br>
    '.carousel().'
    </div>
    <div id="manager_list_names_by_letter" class="container-fluid " style="text-align:center;">
    <br>
 
    </div>

    ';

    return $html2return;
}

function show_usersdata()
{
    session_start();
    $usersdatas=$_SESSION['userdatas'];
    session_write_close();

    $html2return='
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-body p-4 p-md-5">
                        '.show_usersdata_table($usersdatas, 1).'
                    </div>
                    <div class="card-footer bg-light text-center py-3 border-0 rounded-bottom-4">
                        <span class="badge bg-info text-dark rounded-pill px-3 py-2 fs-6 shadow-sm">
                            <i class="bi bi-info-circle me-1"></i> Τα στοιχεία προέρχονται από το <BR><strong>myschool.gr</strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <br><br>
    ';

    return $html2return;
}

function pdf_field($label, $value)
{
    return '
    <tr>
        <td style="padding:4px 0;border-bottom:1px solid #dcdcdc;">
            <span style="font-size:8px;color:#777777;">
                '.mb_strtoupper($label,'UTF-8').'
            </span><br>
            <span style="font-size:11px;font-weight:bold;color:#000000;">
                '.$value.'
            </span>
        </td>
    </tr>';
}


function show_usersdata_table4pdf($usersdatas, $show_th=1)
{
    $am_html = '';
    if(($usersdatas['AM']!=0)&&($usersdatas['AM']!=''))
    {
        $am_html = '
        <div class="col-12 mb-1">
            <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Αρ. Μητρώου</span>
            <strong class="text-dark fs-6">'.$usersdatas['AM'].'</strong>
        </div>';
    }
    
    $header_html = '';
    if($show_th==1) {
        $header_html = '<h5 class="fw-bold text-primary mb-3 pb-2 border-bottom"><i class="bi bi-person-vcard me-2"></i>Στοιχεία Χρήστη</h5>';
    }


$html2return='

             <table width="100%" cellpadding="1">
                '.pdf_field('ΕΠΩΝΥΜΟ',$usersdatas['SNAME']).'
                '.pdf_field('ΟΝΟΜΑ',$usersdatas['FNAME']).'
                '.pdf_field('ΠΑΤΡΩΝΥΜΟ',$usersdatas['FANAME']).'
                '.pdf_field('ΑΦΜ',afmed($usersdatas['AFM'])).'
                '.pdf_field('ΑΜ',$usersdatas['AM']).'
                '.pdf_field('ΕΙΔΙΚΟΤΗΤΑ',$usersdatas['KLADOS']).'
                '.pdf_field('ΣΧΕΣΗ ΕΡΓΑΣΙΑΣ',$usersdatas['JOB_STATUS']).'
                '.pdf_field(
                    'ΟΡΓΑΝΙΚΗ / ΠΡΟΣΩΡΙΝΗ ΤΟΠΟΘΕΤΗΣΗ',
                    check_diathesi(
                        afmed($usersdatas['AFM']),
                        $usersdatas['ORGANIKI']
                    )
                ).'
                '.pdf_field('ΚΙΝΗΤΟ',$usersdatas['MOBILE']).'
                '.pdf_field('Email',$usersdatas['EMAIL']).'
            </table>
';
    return $html2return;
}


function show_usersdata_table_1_COLUMN($usersdatas, $show_th=1)
{
    $am_html = '';
    if(($usersdatas['AM']!=0)&&($usersdatas['AM']!=''))
    {
        $am_html = '
        <div class="col-12 mb-1">
            <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Αρ. Μητρώου</span>
            <strong class="text-dark fs-6">'.$usersdatas['AM'].'</strong>
        </div>';
    }
    
    $header_html = '';
    if($show_th==1) {
        $header_html = '<h5 class="fw-bold text-primary mb-3 pb-2 border-bottom"><i class="bi bi-person-vcard me-2"></i>Στοιχεία Χρήστη</h5>';
    }

    $html2return='
    <div class="user-data-wrapper text-start">
        '.$header_html.'
        <div class="row g-1">
            <div class="col-12 mb-1">
                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Επώνυμο</span>
                <strong class="text-dark fs-6">'.$usersdatas['SNAME'].'</strong>
            </div>
            <div class="col-12 mb-1">
                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Όνομα</span>
                <strong class="text-dark fs-6">'.$usersdatas['FNAME'].'</strong>
            </div>
            <div class="col-12 mb-1">
                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Πατρώνυμο</span>
                <strong class="text-dark fs-6">'.$usersdatas['FANAME'].'</strong>
            </div>
            <div class="col-12 mb-1">
                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">ΑΦΜ</span>
                <strong class="text-dark fs-6">'.afmed($usersdatas['AFM']).'</strong>
            </div>
            '.$am_html.'
            <div class="col-12 mb-1">
                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Ειδικότητα</span>
                <strong class="text-dark fs-6">'.$usersdatas['KLADOS'].'</strong>
            </div>
            <div class="col-12 mb-1">
                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Σχέση Εργασίας</span>
                <strong class="text-dark fs-6">'.$usersdatas['JOB_STATUS'].'</strong>
            </div>
            <div class="col-12 mb-1">
                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Οργανική / Προσ. Τοποθ.</span>
                <strong class="text-dark fs-6">'.check_diathesi(afmed($usersdatas['AFM']),$usersdatas['ORGANIKI']).'</strong>
            </div>
            <div class="col-12 mb-1">
                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">Κινητό</span>
                <strong class="text-dark fs-6">'.$usersdatas['MOBILE'].'</strong>
            </div>
            <div class="col-12">
                <span class="text-muted small d-block text-uppercase fw-semibold" style="font-size: 0.7rem;">e-Mail</span>
                <strong class="text-primary fs-6">'.$usersdatas['EMAIL'].'</strong>
            </div>
        </div>
    </div>
    ';
    
    return $html2return;
}

function show_usersdata_table($usersdatas, $show_th=1)
{
    $am_html = '';
    if(($usersdatas['AM']!=0)&&($usersdatas['AM']!=''))
    {
        $am_html = '
        <div class="col-sm-6 mb-3">
            <span class="text-muted small d-block text-uppercase fw-semibold">Αρ. Μητρώου</span>
            <strong class="text-dark fs-6">'.$usersdatas['AM'].'</strong>
        </div>';
    }
    
    $header_html = '';
    if($show_th==1) {
        $header_html = '<h4 class="fw-bold text-primary mb-4 border-bottom pb-3"><i class="bi bi-person-vcard me-2"></i>Στοιχεία Χρήστη</h4>';
    }

    $html2return='
    <div class="user-data-wrapper text-start">
        '.$header_html.'
        <div class="row">
            <div class="col-sm-6 mb-3">
                <span class="text-muted small d-block text-uppercase fw-semibold">Επώνυμο</span>
                <strong class="text-dark fs-6">'.$usersdatas['SNAME'].'</strong>
            </div>
            <div class="col-sm-6 mb-3">
                <span class="text-muted small d-block text-uppercase fw-semibold">Όνομα</span>
                <strong class="text-dark fs-6">'.$usersdatas['FNAME'].'</strong>
            </div>
            <div class="col-sm-6 mb-3">
                <span class="text-muted small d-block text-uppercase fw-semibold">Πατρώνυμο</span>
                <strong class="text-dark fs-6">'.$usersdatas['FANAME'].'</strong>
            </div>
            <div class="col-sm-6 mb-3">
                <span class="text-muted small d-block text-uppercase fw-semibold">ΑΦΜ</span>
                <strong class="text-dark fs-6">'.afmed($usersdatas['AFM']).'</strong>
            </div>
            '.$am_html.'
            <div class="col-sm-6 mb-3">
                <span class="text-muted small d-block text-uppercase fw-semibold">Ειδικότητα</span>
                <strong class="text-dark fs-6">'.$usersdatas['KLADOS'].'</strong>
            </div>
            <div class="col-sm-6 mb-3">
                <span class="text-muted small d-block text-uppercase fw-semibold">Σχέση Εργασίας</span>
                <strong class="text-dark fs-6">'.$usersdatas['JOB_STATUS'].'</strong>
            </div>
            <div class="col-sm-6 mb-3">
                <span class="text-muted small d-block text-uppercase fw-semibold">Οργανική / Προσ. Τοποθ.</span>
                <strong class="text-dark fs-6">'.check_diathesi(afmed($usersdatas['AFM']),$usersdatas['ORGANIKI']).'</strong>
            </div>
            <div class="col-sm-6 mb-3">
                <span class="text-muted small d-block text-uppercase fw-semibold">Κινητό</span>
                <strong class="text-dark fs-6">'.$usersdatas['MOBILE'].'</strong>
            </div>
            <div class="col-12 mb-2">
                <span class="text-muted small d-block text-uppercase fw-semibold">e-Mail</span>
                <strong class="text-primary fs-6">'.$usersdatas['EMAIL'].'</strong>
            </div>
        </div>
    </div>
    ';
    
    return $html2return;
}

function show_usersdata_deprecated()
{
    session_start();
    $usersdatas=$_SESSION['userdatas'];
    $all_sessionsdata=$_SESSION;
    $sch_data=$_SESSION['sch_datas'];
    session_write_close();

    $html2return='
    <div id="usersdata" class="container-fluid ">
    <br><br><br>
    '.show_usersdata_table($usersdatas).'
        <br>
 
    </div>
    <div class="container-fluid ">
        <p class="alert alert-info" style="text-align:center;"> Τα στοιχεία προέρχονται από το <strong>myschool.gr</strong> </p>
    </div>

    ';
//'.show_array($sch_data).'<hr>'.show_array($usersdatas).'
    return $html2return.'<br><hr><br>';
}

function show_usersdata_table4pdf2($usersdatas,$show_th=1)
{
    if($show_th==1)
    {
        $th='
        <thead>
        <tr>
            <th colspan="2" style="text-align:center;">
                Στοιχεία Χρήστη
            </th>
        </tr>
        </thead)
        ';
        $border='border: 2px solid grey;';
    }
    else
    {
        $th='';
        $border='';
    }
    if(($usersdatas['AM']==0)||($usersdatas['AM']=='')) // check if AM exists and add it to table
    {
        $am='';
    }
    else
    {
        $am='
        <tr>
        <td>
            Αρ. Μητρώου:
        </td>
        <td>
            '.$usersdatas['AM'].'
        </td>
    </tr>';
    }
    $html2return='
    <table class="usersdatas_table" style="margin: auto auto;padding: 5px;'.$border.'">
    '.$th.'
    <tbody>
        <tr>
            <td>
                Επώνυμο:
            </td>
            <td>
                '.$usersdatas['SNAME'].'
            </td>
        </tr>
        <tr>
            <td>
                Όνομα:
            </td>
            <td>
                '.$usersdatas['FNAME'].'
            </td>                    
        </tr>
        <tr>                    
            <td>
                Πατρώνυμο
            </td>
            <td>
            '.$usersdatas['FANAME'].'
            </td>
        </tr>

        <tr>
            <td>
                ΑΦΜ:
            </td>
            <td>
            '.afmed($usersdatas['AFM']).'
            </td>
        </tr>
        '.$am.'
        <tr>
            <td>
                Ειδικότητα :
            </td>
            <td>
            '.$usersdatas['KLADOS'].'
            </td>
        </tr>
        <tr>
            <td>
                Σχέση Εργασίας :
            </td>
            <td>
            '.$usersdatas['JOB_STATUS'].'
            </td>
        </tr>
        <tr>
            <td>
                Οργανική/Προσ. Τοποθ.:
            </td>
            <td>
            '.check_diathesi(afmed($usersdatas['AFM']),$usersdatas['ORGANIKI']).'
            </td>
        </tr>
        <tr>
            <td>
                Κινητό :
            </td>
            <td>
            '.$usersdatas['MOBILE'].'
            </td>     
        </tr>
        <tr>
            <td>
                e-Mail :
            </td>
            <td>
            '.$usersdatas['EMAIL'].'
            </td>     
    </tr>
    </tbody>
</table>
    ';
    return $html2return;
}

function new_applications_menu()
{
    global $srv_conf;
    session_start();
    $usersdatas = $_SESSION['userdatas'];
    $usertype = $_SESSION['usertype'];
    session_write_close();
    
    $existing_datas = load_permanent_applications_data();
    
    $priority_html = ''; // Για id >= 100
    $normal_html = '';   // Για id < 100

    foreach($srv_conf['applications_permanent'] as $id => $application_name)
    {
        if($id == 6) continue;
        
        $db_application_id = 0;
        $status_badge = '<span class="badge bg-light text-secondary border">Νέα</span>';
        $status_class = "border-primary";

        if(isset($existing_datas[$id])) {
            if($existing_datas[$id]['FINAL'] == 0) {
                $status_badge = '<span class="badge bg-warning text-dark"><i class="bi bi-pencil-square"></i> Προσωρινά Αποθηκευμένη</span>';
                $status_class = "border-warning";
            } else {
                $status_badge = '<span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Υποβλήθηκε</span>';
                $status_class = "border-success";
                $db_application_id = $existing_datas[$id]['db_application_id'];
            }
        }

        // Έλεγχος διαθεσιμότητας
        if(((application_available_am_check($id)==1)||(application_available_afm_check($id)==1))&&(app_infuture($id)))
        {
            // Κατασκευή του HTML για το card
            $card_html = '
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 '.$status_class.' border-start border-5 rounded-3 hover-shadow" 
                     onclick="new_permanent_application('.$id.','.$db_application_id.');" 
                     style="cursor: pointer; transition: transform 0.2s;">
                    <div class="card-body">
                        <h5 class="card-title text-primary fw-bold mb-3">'.($id >= 100 ? '<i class="bi bi-star-fill text-warning me-1"></i>' : '').$application_name.'</h5>
                        <p class="card-text text-muted small">'.($srv_conf['applications_comments'][$id] ?? '').'</p>
                    </div>
                    <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
                        '.$status_badge.'
                        <button class="btn btn-sm btn-outline-primary rounded-pill">Επιλογή</button>
                    </div>
                </div>
            </div>';

            // Διαχωρισμός: αν είναι >= 100 πάει στο priority_html, αλλιώς στο normal_html
            if ($id >= 100) {
                $priority_html .= $card_html;
            } else {
                $normal_html .= $card_html;
            }
        }
    }

    return '
    <div class="container py-4">
        <h2 class="mb-4 text-dark fw-bold"><i class="bi bi-file-earmark-plus me-2"></i>Επιλογή Νέας Αίτησης</h2>
        
        <div class="row g-4 mb-4">
            '.$priority_html.'
        </div>
        
        '.($normal_html ? '<hr class="my-4"><h5 class="text-muted mb-3">Λοιπές Αιτήσεις</h5><div class="row g-4">'.$normal_html.'</div>' : '').'
        
        '.((!$priority_html && !$normal_html) ? '<div class="col-12 text-center py-5 text-muted">Δεν υπάρχουν διαθέσιμες αιτήσεις αυτή τη στιγμή.</div>' : '').'
    </div>';
}

function new_applications_menu_all_apps_the_same()
{
    global $srv_conf;
    session_start();
    $usersdatas=$_SESSION['userdatas'];
    $usertype=$_SESSION['usertype'];
    session_write_close();
    
    $existing_datas=load_permanent_applications_data();
    
    $html_grid = '';

    foreach($srv_conf['applications_permanent'] as $id => $application_name)
    {
        if($id == 6) continue; // Skip if needed
        
        $db_application_id = 0;
        $status_badge = '<span class="badge bg-light text-secondary border">Νέα</span>';
        $status_class = "border-primary";

        if(isset($existing_datas[$id])) {
            if($existing_datas[$id]['FINAL'] == 0) {
                $status_badge = '<span class="badge bg-warning text-dark"><i class="bi bi-pencil-square"></i> Προσωρινά Αποθηκευμένη</span>';
                $status_class = "border-warning";
            } else {
                $status_badge = '<span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Υποβλήθηκε</span>';
                $status_class = "border-success";
                $db_application_id = $existing_datas[$id]['db_application_id'];
            }
        }

        // Έλεγχος διαθεσιμότητας
        if(((application_available_am_check($id)==1)||(application_available_afm_check($id)==1))&&(app_infuture($id)))
        {
            $html_grid .= '
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 '.$status_class.' border-start border-5 rounded-3 hover-shadow" 
                     onclick="new_permanent_application('.$id.','.$db_application_id.');" 
                     style="cursor: pointer; transition: transform 0.2s;">
                    <div class="card-body">
                        <h5 class="card-title text-primary fw-bold mb-3">'.$application_name.'</h5>
                        <p class="card-text text-muted small">'.($srv_conf['applications_comments'][$id] ?? '').'</p>
                    </div>
                    <div class="card-footer bg-white border-0 d-flex justify-content-between align-items-center">
                        '.$status_badge.'
                        <button class="btn btn-sm btn-outline-primary rounded-pill">Επιλογή</button>
                    </div>
                </div>
            </div>';
        }
    }

    return '
    <div class="container py-4">
        <h2 class="mb-4 text-dark fw-bold"><i class="bi bi-file-earmark-plus me-2"></i>Επιλογή Νέας Αίτησης</h2>
        <div class="row g-4">
            '.($html_grid ?: '<div class="col-12 text-center py-5 text-muted">Δεν υπάρχουν διαθέσιμες αιτήσεις αυτή τη στιγμή.</div>').'
        </div>
    </div>';
}

function new_applications_menu_deprecated()
{
    global $srv_conf;
    session_start();
    $usersdatas=$_SESSION['userdatas'];
    $domestic=$usersdatas['ORGANIKIbyID'];
    $usertype=$_SESSION['usertype'];
    session_write_close();
    if(($usertype=='manager')||($usertype=='secretary')){$admin_logon=1;}else{$admin_logon=0;} // chech admin logon 2 allow all buttons


    $existing_datas=load_permanent_applications_data();

    $new_permanent_apllication_list_trows='';

    foreach($srv_conf['applications_permanent'] as $id=>$application_name)
    {
        $db_application_id=0;

        //if((isset($existing_datas[$id]))&&(app_intime($id,$existing_datas[$id]['time_last_modified'])))
        $date_applied_html='';
        if(isset($existing_datas[$id]))
        {
            if(isset($existing_datas[$id]['time_last_modified']))
                {
                    $date_applied=date("d/m/Y H:i:s",$existing_datas[$id]['time_last_modified']);
                    $date_applied_html='<br><small><small>'.$date_applied.'</small></small>';
                }
                else{$date_applied_html='';}

            if($existing_datas[$id]['FINAL']==0)
            {$existing_application='Αποθηκευμένη'.$date_applied_html;}
            else
            {

                $existing_application='Υποβλήθηκε'.$date_applied_html;
                $db_application_id=$existing_datas[$id]['db_application_id'];
            }
            $existing_application='<td style="color:brown; font-style:italic; padding-right:5px;text-align:center;">'.$existing_application.'</td>';
            $colspan='';
        }
        else
        {
            $existing_application='';
            $colspan=' colspan="2" ';
        }
        //if(($id<=8)&&(subnet_login()==0)){continue;}
        if($id==6){continue;}
        //if(($id==103)&&(subnet_login()!=1)&&($domestic==0)){continue;}
        $myafm=$usersdatas['AFM'];
        //if(($id==103)&&($domestic==0)&&($admin_logon==0)&&($usersdatas['AFM']!='070322960')&&($usersdatas['AFM']!='149213947')){continue;} // Προβολή button αίτησης μόνο στους έχοντες οργανική στην ΔΔΕ και στους admins
        
        //if(($id==104)&&(subnet_login()!=1)){continue;}
        if($id>=100)
        {
            //logdata(application_available_am_check($id).' '.application_available_afm_check($id).' '.app_infuture($id).' id='.$id);
            if(((application_available_am_check($id)==1)||(application_available_afm_check($id)==1))&&(app_infuture($id)))
            {
                $new_permanent_apllication_list_trows='
                <tr onclick="new_permanent_application('.$id.','.$db_application_id.');" style="cursor: pointer;">
                <td '.$colspan.' style="text-align:left;padding-left:5px; padding-right: 5px;">
                    <button class="button02" style="width:100%;text-align:left;">'.$application_name.'</button>
                </td>
                    '.$existing_application.'
                </tr>
                <tr><td colspan="2"><hr></td></tr>'.$new_permanent_apllication_list_trows;    
            }
        }
        else
        {
        $new_permanent_apllication_list_trows.='
            <tr onclick="new_permanent_application('.$id.','.$db_application_id.');" style="cursor: pointer;">
                <td '.$colspan.' style="text-align:left;padding-left:5px; padding-right: 5px;">
                    <button class="button02" style="width:100%;text-align:left;">'.$application_name.'</button>
                </td>
                
                '.$existing_application.'
            </tr>
        ';
        }
    }

    


    $checkadd='
    <tr>
                    <td colspan="2">
                    '.show_array($existing_datas).'<br><br>TIME:'.time().'
                    </td>
                </tr>';
    
    $checkadd='';                
    $html2return='
    <div id="usersdata" class="container-fluid ">
    <br>
        <table style="margin: auto auto;border: 2px solid grey;padding: 10px;">
            <thead>
                <tr>
                    <th colspan="2" style="text-align:center;padding-top:10px;padding-bottom:10px;font-size:150%;background-color:whitesmoke;color:darkblue;">
                        Νέα Αίτηση
                    </th>
                </tr>
            </thead)
            <tbody>
                '.$new_permanent_apllication_list_trows.'
                '.$checkadd.'
            </tbody>
        </table>
        <br><br><br>';

    return $html2return;
}

function applications_history()
{
    global $srv_conf;

    session_start();
    $usersdatas=$_SESSION['userdatas'];
    session_write_close();

    $afm=$usersdatas['AFM'];

    $html2return='
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Ιστορικό Αιτήσεων</h4>
            </div>
            <div class="card-body p-4 table-responsive">
                <table class="table table-striped table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 60px;">Α/Α</th>
                            <th>Θέμα Αίτησης</th>
                            <th class="text-center">Αριθμ. Πρωτοκόλλου</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    $sql="SELECT * FROM APPLICATIONS WHERE AFM='$afm' AND PROTOCOL_ID>0 ORDER BY PROTOCOL_DATE DESC";
    $result=dbq($sql);
    $aa=1;
    
    $rows_html = '';
    while($row=mysqli_fetch_array($result))
    {
        $rows_html.='
                <tr onclick="new_permanent_application('.$row['CATEGORY'].','.$row['ID'].');" style="cursor:pointer;" title="Προβολή Αίτησης">
                    <td class="text-center fw-bold text-muted">'.$aa++.'</td>
                    <td class="fw-bold text-primary">'.$srv_conf['applications_permanent'][$row['CATEGORY']].'</td>
                    <td class="text-center">
                        <span class="badge bg-secondary fs-6 shadow-sm">
                            '.$row['PROTOCOL_ID'].' / '.formdate($row['PROTOCOL_DATE']).'
                        </span>
                    </td>
                </tr>
        ';
    }
    
    if($aa==1)
    {
        $rows_html = '
            <tr>
                <td colspan="3" class="text-center py-4 text-muted fst-italic">
                    <i class="bi bi-info-circle me-2"></i> Δεν υπάρχουν Πρωτοκολλημένες Αιτήσεις στο ιστορικό σας.
                </td>
            </tr>
        ';
    }

    $html2return .= $rows_html . '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';

    return $html2return;
}

function applications_history_deprecated()
{
    global $srv_conf;

    session_start();
    $usersdatas=$_SESSION['userdatas'];
    session_write_close();

    $afm=$usersdatas['AFM'];

    $html2return='
    <div id="usersdata" class="container-fluid " style="text-align:center;">
    <br>
    <h3> Ιστορικό Αιτήσεων </h3>
    <br>
        <table class="usersdatas_table" style="margin: auto auto;border: 2px solid grey;padding: 10px;">
            <thead>
                <tr style="background-color:#F5F5F5;">
                    <th colspan="2" style="text-align:center;">
                        Θέμα
                    </th>
                    <th style="text-align:center;">
                        Αριθμ. Πρωτ.
                    </th>
                </tr>
            </thead)
            <tbody>';
    $sql="SELECT * FROM APPLICATIONS WHERE AFM='$afm' AND PROTOCOL_ID>0";
    $result=dbq($sql);
    $aa=1;
    while($row=mysqli_fetch_array($result))
    {
        $id=$row['CATEGORY'];
        $db_application_id=$row['ID'];
        $html2return.='
                <tr onclick="new_permanent_application('.$id.','.$db_application_id.');" " style="cursor:pointer;" title="Προβολή Αίτησης - '.$srv_conf['applications_permanent'][$row['CATEGORY']].'">
                    <td>
                    '.$aa++.'
                    </td>
                    <td style="text-align:center;">
                        '.$srv_conf['applications_permanent'][$row['CATEGORY']].'
                    </td>
                    <td style="text-align:center;padding-left:15px;">
                    '.$row['PROTOCOL_ID'].'/'.formdate($row['PROTOCOL_DATE']).'
                    </td>
                </tr>
        ';
    }
    if($aa==1)
    {
        $html2return.='
            <tr>
                <td colspan="3" style="text-align:center;font-style:italic;">
                    Δεν υπάρχουν Πρωτοκολλημένες Αιτήσεις
                </td>
            </tr>
        ';
    }

    $html2return.='
            </tbody>
        </table>';

    return $html2return;
}
 

function formdate($d) // change date string from 2024-08-25 to 25-08-2024
{
    $expl_d=explode('-',$d);
    return $expl_d[2].'-'.$expl_d[1].'-'.$expl_d[0];
}


function carousel_deprecated()
{
    $html2return='';
    $snames_first_letter=array();
    $sql="SELECT * FROM USERS";
    $result=dbq($sql);
    while($row=mysqli_fetch_array($result))
    {
        $snames_first_letter[mb_substr($row['SNAME'],0,1)]++;
    }

    ksort($snames_first_letter);
    foreach($snames_first_letter as $letter=>$hits)
    {
        $eng_flag='';
        if (!preg_match('/[^A-Za-z0-9]/', $letter)){$eng_flag='english_flag';}
        $html2return.='<a href="#" class="carousel '.$eng_flag.'" onclick="show_letter_list(\''.$letter.'\')";" title="'.$hits.' Χρήστες">'.$letter.'</a> ';
    }
    //session_start();
    //unset($_SESSION['userdatas']);
    //$all_session=$_SESSION;
    //session_write_close();
    return $html2return;

}

function show_snames($letter)
{
    $aa=1;
    $html2return='
        <div class="card shadow-sm border-0 rounded-4 mt-3">
            <div class="card-header bg-secondary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Χρήστες με γράμμα: "'.$letter.'"</h5>
            </div>
            <div class="card-body p-2 p-md-4">
                <div class="accordion shadow-sm" id="accordionUsers">';
                
    $sql="SELECT * FROM USERS WHERE SNAME LIKE '$letter%' ORDER BY SNAME,FNAME ASC";
    $result=dbq($sql);

    while($row=mysqli_fetch_array($result))
    {
        $afm_clean = afmed($row['AFM']);
        $collapse_id = 'collapse_' . $afm_clean;
        $heading_id = 'heading_' . $afm_clean;
        
        $html2return.='
        <div class="accordion-item border-0 border-bottom">
            <h2 class="accordion-header" id="'.$heading_id.'">
                <button class="accordion-button collapsed fw-bold text-dark fs-5" type="button" data-bs-toggle="collapse" data-bs-target="#'.$collapse_id.'" aria-expanded="false" aria-controls="'.$collapse_id.'">
                    <span class="badge bg-primary rounded-pill me-3" style="width: 40px;">'.$aa++.'</span>
                    '.$row['SNAME'].' '.mb_substr($row['FANAME'],0,3).' '.$row['FNAME'].'
                </button>
            </h2>
            <div id="'.$collapse_id.'" class="accordion-collapse collapse" aria-labelledby="'.$heading_id.'" data-bs-parent="#accordionUsers">
                <div class="accordion-body bg-light rounded-bottom-3 p-3">
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            '.show_usersdata_table($row, 0).'
                        </div>
                        <div class="col-md-3 text-center mt-3 mt-md-0 border-md-start">
                            <button class="btn btn-warning fw-bold shadow-sm w-100 py-3" onclick="manager_connect2user(\''.$row['AFM'].'\');">
                                <i class="bi bi-box-arrow-in-right fs-3 d-block mb-1"></i>
                                Σύνδεση ως<br><small class="text-dark">'.$row['AFM'].'</small>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    $html2return.='</div></div></div><br><br><br>';

    return $html2return;
}

function show_snames_deprecated($letter)
{
    $aa=1;
    $html2return='
        <br>
        <h4>            Πίνακας χρηστών <strong>"'.$letter.'" </strong> </h4>
        <table style="text-align:center;border:2px solid green;">
            <thead>
                <tr>
                    <th style="text-align:center;">
                        AA
                    </th>
                    <th style="text-align:center;">
                        Ονοματεπώνυμο
                    </th>
                    <!--
                    <th style="text-align:center;">
                        Α.Φ.Μ.
                    </th>
                    -->
                    <th colspan="2">
                    </th>
                </tr>
            </thead>
            <tbody>
        ';
    $snames_by_letter=array();
    $sql="SELECT * FROM USERS WHERE SNAME LIKE '$letter%' ORDER BY SNAME,FNAME ASC";
    $result=dbq($sql);

    while($row=mysqli_fetch_array($result))
    {
        $html2return.='
            <tr onclick="display(\''.afmed($row['AFM']).'\');toggle_yellow(this);" style="cursor: pointer;">
                <td>'.$aa++.'</td>
                <td style="padding-left:10px; padding-right:10px;text-align:left;">'.$row['SNAME'].' '.mb_substr($row['FANAME'],0,3).' '.$row['FNAME'].'</td>
                <!--<td>'.afmed($row['AFM']).'</td> -->
                <td id="'.afmed($row['AFM']).'"style="width:50%;display:none;">
                    <table>
                        <tr>
                            <td>
                                '.show_usersdata_table($row).'
                            </td>
                            <td style="padding-left:20px;">
                                <button class="button01" onclick="manager_connect2user(\''.$row['AFM'].'\');">Σύνδεση ΑΦΜ: '.$row['AFM'].' </button>
                            </td>
                        </tr>
                    </table>
                </td>

            </tr>
            ';
    }

    $html2return.='</tbody></table><br><br><br>';

    return $html2return;
}

function show_managers_history()
{
    global $srv_conf;
    
    $html2return='
    <div class="container-fluid mt-4 mb-5">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Ιστορικό Αιτήσεων Χρηστών</h4>
            </div>
            <div class="card-body p-4 table-responsive">
    ';

    $html2return_applications_table='
        <table id="history_table" class="table table-striped table-hover table-bordered align-middle w-100">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width: 60px;">Α/Α</th>
                    <th>Ονοματεπώνυμο</th>
                    <th>Θέμα Αίτησης</th>
                    <th class="text-center" style="width: 150px;">Πρωτόκολλο</th>
                    <th class="text-center" style="width: 120px;">Ενέργειες</th>
                </tr>
            </thead>
            <tbody>
    ';
    
    $sql="SELECT * FROM APPLICATIONS WHERE PROTOCOL_ID<>0 ORDER BY ID DESC";
    $result=dbq($sql);
    
    $total_rows = mysqli_num_rows($result);
    $aa = $total_rows;
    
    $tbody='';

    while($row=mysqli_fetch_array($result))
    {
        $onclick='onclick="show_managers_permanent_application(\''.$row['AFM'].'\',\''.$row['ID'].'\');" style="cursor:pointer;"';
        $userdata=afm_data($row['AFM']);
        
        $current_aa = $aa--; 
        
        $tbody_row='
            <tr>
                <td class="text-center fw-bold text-muted" data-order="'.$current_aa.'" '.$onclick.'>
                    '.$current_aa.'
                </td>
                <td '.$onclick.'>
                    <div class="fw-bold text-primary">'.$userdata['fullname'].'</div>
                    <div class="small text-muted"><i class="bi bi-person-vcard me-1"></i>ΑΦΜ: '.$row['AFM'].'</div>
                </td>
                <td '.$onclick.'>
                    '.$srv_conf['applications_permanent'][$row['CATEGORY']].'
                </td>
                <td class="text-center" data-order="'.$row['PROTOCOL_ID'].'" '.$onclick.'>
                    <span class="badge bg-secondary fs-6 shadow-sm">'.$row['PROTOCOL_ID'].'/'.formdate($row['PROTOCOL_DATE']).'</span>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary shadow-sm" onclick="sendmail_protocoled_application(\''.$row['ID'].'\');" title="Αποστολή Mail στην Γραμματεία">
                        <i class="bi bi-envelope-paper"></i> Mail
                    </button>
                </td>
            </tr>
        ';
        $tbody.=$tbody_row;
    }

    $html2return_applications_table .= $tbody;
    
    if($total_rows == 0)
    {
        $html2return_applications_table='
            <div class="alert alert-info text-center my-4 fs-5">
                <i class="bi bi-info-circle me-2"></i> Δεν υπάρχουν Αιτήσεις στο Ιστορικό
            </div>
        ';
    }
    else
    {
        $html2return_applications_table.='
                </tbody>
            </table>';
    }
    
    $html2return2='
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        var element = document.getElementById("history_table");
        if(typeof(element) != "undefined" && element != null) {
            
            if ($.fn.DataTable.isDataTable("#history_table")) {
                $("#history_table").DataTable().destroy();
            }
            
            $("#history_table").DataTable({
                paging: true,
                pageLength: 30,
                lengthMenu: [
                    [30, 50, 80, -1],
                    [30, 50, 80, "Όλες"]
                ],
                // ΕΔΩ ΕΙΝΑΙ Η ΛΥΣΗ: Επιβάλλουμε αριθμητικό τύπο (num) στη στήλη 0 (Α/Α)
                columnDefs: [
                    { type: "num", targets: 0 } 
                ],
                order: [[ 0, "desc" ]], // Ταξινομεί φθίνουσα (μεγαλύτερος αριθμός πρώτα)
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/el.json"
                },
                dom: "<\'row mb-3\'<\'col-sm-12 col-md-6\'l><\'col-sm-12 col-md-6\'f>>" +
                     "<\'row\'<\'col-sm-12\'tr>>" +
                     "<\'row mt-3\'<\'col-sm-12 col-md-5\'i><\'col-sm-12 col-md-7\'p>>",
                buttons: [\'excelHtml5\']
            });
        }
    });
    </script>
    ';

    return $html2return.$html2return_applications_table.$html2return2;    
}

function show_managers_history_depricated()
{
    global $srv_conf;
    $html2return='
    <div id="usersdata" class="container-fluid " style="text-align:center;">
    <br>
    <div style="margin:auto auto;padding:10px;background-color:whitesmoke;">
        <h2> Ιστορικό Αιτήσεων Χρηστών</h2>
        <br>';

    $html2return_applications_table='
    <table id="history_table" class="change_background" style="width:75%;margin: auto auto;padding: 10px;">
        <thead>
            <tr style="background:darkblue;color:white;">
                <th style="text-align:center;">
                    AA
                </th>
                <th style="text-align:center;">
                    Ονοματεπώνυμο
                </th>
                <th style="text-align:center;">
                    Θέμα
                </th>
                <th style="text-align:center;">
                    Πρωτόκολλο
                </th>
                <th style="text-align:center;font-size:85%;">
                    mail στη Γραμματεία
                </th>
            </tr>
        </thead>
        <tbody>
                   
                ';
    
    $aa=1;
    $sql="SELECT * FROM APPLICATIONS WHERE PROTOCOL_ID<>0 ";
    $result=dbq($sql);
    $tbody='';

    while($row=mysqli_fetch_array($result))
    {
        //$date=date("d/m/Y H:i:s",json_decode($row['DATAS'],true)['time_last_modified']);
        $onclick='onclick="show_managers_permanent_application(\''.$row['AFM'].'\',\''.$row['ID'].'\',);" style="cursor:pointer;"';
        $userdata=afm_data($row['AFM']);
        $tbody_row='
            <tr>
                <td style="text-align:center;" '.$onclick.' >
                    '.$aa++.'
                </td>
                <td '.$onclick.'>
                    '.$userdata['fullname'].'
                </td>
                <td  '.$onclick.'>
                    '.$srv_conf['applications_permanent'][$row['CATEGORY']].'
                </td>
                <td  '.$onclick.' style="text-align:center;" >
                    '.$row['PROTOCOL_ID'].'/'.formdate($row['PROTOCOL_DATE']).' 
                </td>
                <td style="text-align:center;">
                    <button name="sendmail" onclick="sendmail_protocoled_application(\''.$row['ID'].'\');" title="Αποστολή Mail στην Γραμματεία"> Mail </button>
                </td>
            </tr>
            ';
        $tbody.=$tbody_row;
    }

    $html2return_applications_table.=$tbody;
    if($aa==1)
        {
            $html2return_applications_table='
                <p style="text-align:center;font-style:italic;">
                    Δεν υπάρχουν Αιτήσεις στο Ιστορικό
                </p>
                ';
        }
        else
        {
            $html2return_applications_table.='
                </tbody>
            </table>';
        }
    
    $html2return2='
        </div>
        </div>

        <script>

        var element = document. getElementById("history_table");
        //If it isnot "undefined" and it isnot "null", then it exists.
        if(typeof(element) != \'undefined\' && element != null)
            {
                $.fn.DataTable.ext.pager.numbers_length = 7;
                $(\'#history_table\').DataTable({
                    paging: true,
                    iDisplayLength:30,
                    lengthMenu: [
                        [30, 50, 80, -1],
                        [30, 50, 80, \'All\']
                    ],
                    "aaSorting": [[ 0, "desc" ]],
                    buttons: [\'excelHtml5\']
                });
            }

        </script>

        <br><br><br><br><br>
        ';

    return $html2return.$html2return_applications_table.$html2return2;    
}

function get_schools_list_of_db_jdatas($jdatas)
{
    global $school;
    $choosen_schools=array();
    foreach($jdatas as $aa=>$sch)
    {
        $explode_aa=explode('_',$aa);
        if(($explode_aa[0]=='choosen')&&($explode_aa[1]=='school'))
        {$choosen_schools[]=$school[$sch];}

    }
    return $choosen_schools;
}


function excel_app100()
{
    $datas=array();
    $sql="SELECT * FROM APPLICATIONS WHERE CATEGORY=100";
    $result01=dbq($sql);
    $row_counter=0;
    $max_choices=0;
    while($row=mysqli_fetch_array($result01))
    {
        $row_counter++;
        $userdatas=afm_data($row['AFM']);
        $jdatas=json_decode($row['DATAS'],true);
       
        if($row['PROTOCOL_ID']==0)
        {$protocol='δεν πρωτοκολλήθηκε';}
        else
        {$protocol=$row['PROTOCOL_ID'].'/'.formdate($row['PROTOCOL_DATE']);}
        
        if($jdatas['yperari8mos']==1)
        {$yperar='Επιθυμεί';}
        else{{$yperar='ΔΕΝ Επιθυμεί';}}

        $school_list=get_schools_list_of_db_jdatas($jdatas);
        if(count($school_list)>$max_choices){$max_choices=count($school_list);}
        $datasrow=array($row_counter,$row['AFM'],$userdatas['fullname'],$userdatas['KLADOS'],$protocol,$yperar);

        foreach($school_list as $sch)
        {$datasrow[]=$sch;}
        $datas[]=$datasrow;
    }

/*
    $cheaders=array('AA','ΑΦΜ','Ονοματεπώνυμο','Κλάδος','Αριθμ. Πρωτ.','Υπεραριθμία');
    
    for($aa_choices=1;$aa_choices<=$max_choices;$aa_choices++)
    {
        $cheaders[]='Επιλογή '.$aa_choices;
    }
    $spreadsheet = new Spreadsheet();
    $activeWorksheet = $spreadsheet->getActiveSheet();
    $activeWorksheet->fromArray($cheaders, NULL,'A1'); 
    $column_counter='A';
    for($i=1;$i<=count($cheaders);$i++)
    {
        $spreadsheet->getActiveSheet()->getStyle($column_counter.'1')->getFont()->setBold(true);
        $column_counter++;
    }

    $activeWorksheet->fromArray($datas, NULL,'A2'); 

    $writer = new Xlsx($spreadsheet);
    //$writer->save('php://output');
    $writer->save('./uploads/app100.xlsx'); 
*/

require_once __DIR__ . '/classes/php2excel.php';

$baseHeaders = ['AA', 'ΑΦΜ', 'Ονοματεπώνυμο', 'Κλάδος', 'Αριθμ. Πρωτ.', 'Υπεραριθμία'];
// Περνάμε το index 4 (γιατί ο 'Αριθμ. Πρωτ.' είναι το 5ο στοιχείο, ξεκινώντας από το 0)
$php2excel = new PhpToExcel($baseHeaders, $datas, 4);
$php2excel->create('./uploads/app100.xlsx');

//return 'Excel app100.xlsx Created <br>'.$row_counter.' rows exported <br> <a href="./uploads/app100.xlsx" download>Λήψη excel Δηλώσεων Υπεραριθμίας</a><br>';
return 'Excel app100.xlsx Created <br>'.$row_counter.' rows exported <br>  <a href="dl.php?f=app100.xlsx&dir=main" class="btn btn-primary">Λήψη excel</a> <br>';

}

function excel_app101()
{
    $datas=array();
    $sql="SELECT * FROM APPLICATIONS WHERE CATEGORY=101";
    $result01=dbq($sql);
    $row_counter=0;
    $max_choices=0;
    while($row=mysqli_fetch_array($result01))
    {
        $row_counter++;
        $userdatas=afm_data($row['AFM']);
        $jdatas=json_decode($row['DATAS'],true);
       
        if($row['PROTOCOL_ID']==0)
        {$protocol='δεν πρωτοκολλήθηκε';}
        else
        {$protocol=$row['PROTOCOL_ID'].'/'.formdate($row['PROTOCOL_DATE']);}
        
        $school_list=get_schools_list_of_db_jdatas($jdatas);
        if(count($school_list)>$max_choices){$max_choices=count($school_list);}
        $datasrow=array($row_counter,$row['AFM'],$userdatas['fullname'],$userdatas['AM'],$userdatas['KLADOS'],check_diathesi($row['AFM'],$userdatas['ORGANIKI']),$protocol);

        foreach($school_list as $sch)
        {$datasrow[]=$sch;}
        $datas[]=$datasrow;
    }

/*
    $cheaders=array('AA','ΑΦΜ','Ονοματεπώνυμο','AM','Κλάδος','Όργανική','Αριθμ. Πρωτ.');
    
    for($aa_choices=1;$aa_choices<=$max_choices;$aa_choices++)
    {
        $cheaders[]='Επιλογή '.$aa_choices;
    }
    $spreadsheet = new Spreadsheet();
    $activeWorksheet = $spreadsheet->getActiveSheet();
    $activeWorksheet->fromArray($cheaders, NULL,'A1'); 
    $column_counter='A';
    for($i=1;$i<=count($cheaders);$i++)
    {
        $spreadsheet->getActiveSheet()->getStyle($column_counter.'1')->getFont()->setBold(true);
        $column_counter++;
    }

    $activeWorksheet->fromArray($datas, NULL,'A2'); 

    $writer = new Xlsx($spreadsheet);
    //$writer->save('php://output');
    $writer->save('./uploads/app101.xlsx');        
  */
    require_once __DIR__ . '/classes/php2excel.php';

    $baseHeaders = ['AA','ΑΦΜ','Ονοματεπώνυμο','AM','Κλάδος','Όργανική','Αριθμ. Πρωτ.'];
    // Περνάμε το index 6 (γιατί ο 'Αριθμ. Πρωτ.' είναι το 7ο στοιχείο, ξεκινώντας από το 0)
    $php2excel = new PhpToExcel($baseHeaders, $datas, 6);
    $php2excel->create('./uploads/app101.xlsx');

    //return 'Excel app101.xlsx Created <br>'.$row_counter.' rows exported <br> <a href="./uploads/app101.xlsx" download>Λήψη excel Αιτήσεων Βελτίωσης και Οριστικής Τοποθέτησης</a><br>';
    return 'Excel app101.xlsx Created <br>'.$row_counter.' rows exported <br>  <a href="dl.php?f=app101.xlsx&dir=main" class="btn btn-primary">Λήψη excel</a> <br>';
}

function excel_app102()
{
    $datas=array();
    $sql="SELECT * FROM APPLICATIONS WHERE CATEGORY=102";
    $result01=dbq($sql);
    $row_counter=0;
    $max_choices=0;
    while($row=mysqli_fetch_array($result01))
    {
        $row_counter++;
        $userdatas=afm_data($row['AFM']);
        $jdatas=json_decode($row['DATAS'],true);
       
        if($row['PROTOCOL_ID']==0)
        {$protocol='δεν πρωτοκολλήθηκε';}
        else
        {$protocol=$row['PROTOCOL_ID'].'/'.formdate($row['PROTOCOL_DATE']);}
        
        $school_list=get_schools_list_of_db_jdatas($jdatas);
        if(count($school_list)>$max_choices){$max_choices=count($school_list);}
        $datasrow=array($row_counter,$row['AFM'],$userdatas['fullname'],$userdatas['AM'],$userdatas['KLADOS'],check_diathesi($row['AFM'],$userdatas['ORGANIKI']),$protocol);

        foreach($school_list as $sch)
        {$datasrow[]=$sch;}
        $datas[]=$datasrow;
    }
/*

    $cheaders=array('AA','ΑΦΜ','Ονοματεπώνυμο','AM','Κλάδος','Όργανική','Αριθμ. Πρωτ.');
    
    for($aa_choices=1;$aa_choices<=$max_choices;$aa_choices++)
    {
        $cheaders[]='Επιλογή '.$aa_choices;
    }
    $spreadsheet = new Spreadsheet();
    $activeWorksheet = $spreadsheet->getActiveSheet();
    $activeWorksheet->fromArray($cheaders, NULL,'A1'); 
    $column_counter='A';
    for($i=1;$i<=count($cheaders);$i++)
    {
        $spreadsheet->getActiveSheet()->getStyle($column_counter.'1')->getFont()->setBold(true);
        $column_counter++;
    }

    $activeWorksheet->fromArray($datas, NULL,'A2'); 

    $writer = new Xlsx($spreadsheet);
    //$writer->save('php://output');
    $writer->save('./uploads/app102.xlsx');        
  */
    require_once __DIR__ . '/classes/php2excel.php';

    $baseHeaders = ['AA','ΑΦΜ','Ονοματεπώνυμο','AM','Κλάδος','Όργανική','Αριθμ. Πρωτ.'];
    // Περνάμε το index 6 (γιατί ο 'Αριθμ. Πρωτ.' είναι το 7ο στοιχείο, ξεκινώντας από το 0)
    $php2excel = new PhpToExcel($baseHeaders, $datas, 6);
    $php2excel->create('./uploads/app102.xlsx');
    //return 'Excel app102.xlsx Created <br>'.$row_counter.' rows exported <br> <a href="./uploads/app102.xlsx" download>Λήψη excel Αιτήσεων Βελτίωσης και Οριστικής Τοποθέτησης ΕΑΕ</a><br>';
    return 'Excel app102.xlsx Created <br>'.$row_counter.' rows exported <br>  <a href="dl.php?f=app102.xlsx&dir=main" class="btn btn-primary">Λήψη excel</a> <br>';
}



function excel_app103()
{
    $datas=array();
    $sql="SELECT * FROM APPLICATIONS WHERE CATEGORY=103";
    $result01=dbq($sql);
    $row_counter=0;
    $max_choices=0;
    
    while($row=mysqli_fetch_array($result01))
    {
        $row_counter++;
        $userdatas=afm_data($row['AFM']);
        $jdatas=json_decode($row['DATAS'],true);
       
        if($row['PROTOCOL_ID']==0)
        {$protocol='δεν πρωτοκολλήθηκε';}
        else
        {$protocol=$row['PROTOCOL_ID'].'/'.formdate($row['PROTOCOL_DATE']);}
        
        $school_list=get_schools_list_of_db_jdatas($jdatas);
        if(count($school_list)>$max_choices){$max_choices=count($school_list);}
        if($jdatas['kids']==''){$jdatas['kids']='0';}
        if($jdatas['kids_over_18']==''){$jdatas['kids_over_18']='0';}
        
        $datasrow=array(
            $row_counter,
            $row['AFM'],
            $userdatas['fullname'],
            $userdatas['AM'],
            $userdatas['KLADOS'],
            check_diathesi($row['AFM'],$userdatas['ORGANIKI']),
            $protocol,
            $jdatas['merried_status'],
            $jdatas['kids'],
            $jdatas['kids_over_18'],
            $jdatas['locality'],
            $jdatas['coworking'],
            $jdatas['special_cat'],
            $jdatas['diathesi_Abathmia'],
            $jdatas['comments']);

        foreach($school_list as $sch)
        {$datasrow[]=$sch;}
        $datas[]=$datasrow;
    }
    //logdata('Excel 103 asked.'.time().' at row_counter:'.$row_counter.' FINITO.');

    $cheaders=array(
        'AA',
        'ΑΦΜ',
        'Ονοματεπώνυμο',
        'AM',
        'Κλάδος',
        'Όργανική',
        'Αριθμ. Πρωτ.',
        'Οικ.Καταστ.',
        'Ανήλικα Τέκνα',
        'Τέκνα 18-24 ετών',
        'Εντοπιότητα',
        'Συνυπηρέτηση',
        'Ειδ. Κατηγ.',
        'Διάθεση ΑΒαθμια',
        'Παρατηρήσεις');
    /*
    for($aa_choices=1;$aa_choices<=$max_choices;$aa_choices++)
    {
        $cheaders[]='Επιλογή '.$aa_choices;
    }
    $spreadsheet = new Spreadsheet();
    $activeWorksheet = $spreadsheet->getActiveSheet();
    $activeWorksheet->fromArray($cheaders, NULL,'A1'); 
    $column_counter='A';
    for($i=1;$i<=count($cheaders);$i++)
    {
        $spreadsheet->getActiveSheet()->getStyle($column_counter.'1')->getFont()->setBold(true);
        $column_counter++;
    }

    $activeWorksheet->fromArray($datas, NULL,'A2'); 

    $writer = new Xlsx($spreadsheet);
    //$writer->save('php://output');
    $writer->save('./uploads/app103.xlsx');        
    */
    require_once __DIR__ . '/classes/php2excel.php';
    $baseHeaders = $cheaders;
    // Περνάμε το index 6 (γιατί ο 'Αριθμ. Πρωτ.' είναι το 7ο στοιχείο, ξεκινώντας από το 0)
    $php2excel = new PhpToExcel($baseHeaders, $datas, 6);
    $php2excel->create('./uploads/app103.xlsx');

    //return 'Excel app103.xlsx Created <br>'.$row_counter.' rows exported <br> <a href="./uploads/app103.xlsx" download>Λήψη excel Δηλώσεων για Συμπλήρωση Ωραρίου Εκπαιδευτικών με Οργανική θέση στην ΔΔΕ Φλώρινας</a><br>';
    return 'Excel app103.xlsx Created <br>'.$row_counter.' rows exported <br>  <a href="dl.php?f=app103.xlsx&dir=main" class="btn btn-primary">Λήψη excel</a> <br>';
}

function excel_app104()
{
    $datas=array();
    $sql="SELECT * FROM APPLICATIONS WHERE CATEGORY=104";
    $result01=dbq($sql);
    $row_counter=0;
    $max_choices=0;
    while($row=mysqli_fetch_array($result01))
    {
        $row_counter++;
        $userdatas=afm_data($row['AFM']);
        $jdatas=json_decode($row['DATAS'],true);
       
        if($row['PROTOCOL_ID']==0)
        {$protocol='δεν πρωτοκολλήθηκε';}
        else
        {$protocol=$row['PROTOCOL_ID'].'/'.formdate($row['PROTOCOL_DATE']);}
        
        $school_list=get_schools_list_of_db_jdatas($jdatas);
        if(count($school_list)>$max_choices){$max_choices=count($school_list);}
        if($jdatas['kids']==''){$jdatas['kids']='0';}
        if($jdatas['kids_over_18']==''){$jdatas['kids_over_18']='0';}
        
        $datasrow=array(
            $row_counter,
            $row['AFM'],
            $userdatas['fullname'],
            $userdatas['AM'],
            $userdatas['KLADOS'],
            check_diathesi($row['AFM'],$userdatas['ORGANIKI']),
            $protocol,
            $jdatas['merried_status'],
            $jdatas['kids'],
            $jdatas['kids_over_18'],
            $jdatas['locality'],
            $jdatas['coworking'],
            $jdatas['special_cat'],
            $jdatas['diathesi_Abathmia'],
            $jdatas['comments']);

        foreach($school_list as $sch)
        {$datasrow[]=$sch;}
        $datas[]=$datasrow;
    }


    $cheaders=array(
        'AA',
        'ΑΦΜ',
        'Ονοματεπώνυμο',
        'AM',
        'Κλάδος',
        'Όργανική',
        'Αριθμ. Πρωτ.',
        'Οικ.Καταστ.',
        'Ανήλικα Τέκνα',
        'Τέκνα 18-24 ετών',
        'Εντοπιότητα',
        'Συνυπηρέτηση',
        'Ειδ. Κατηγ.',
        'Διάθεση ΑΒαθμια',
        'Παρατηρήσεις');
    /*
    for($aa_choices=1;$aa_choices<=$max_choices;$aa_choices++)
    {
        $cheaders[]='Επιλογή '.$aa_choices;
    }
    $spreadsheet = new Spreadsheet();
    $activeWorksheet = $spreadsheet->getActiveSheet();
    $activeWorksheet->fromArray($cheaders, NULL,'A1'); 
    $column_counter='A';
    for($i=1;$i<=count($cheaders);$i++)
    {
        $spreadsheet->getActiveSheet()->getStyle($column_counter.'1')->getFont()->setBold(true);
        $column_counter++;
    }

    $activeWorksheet->fromArray($datas, NULL,'A2'); 

    $writer = new Xlsx($spreadsheet);
    //$writer->save('php://output');
    $writer->save('./uploads/app104.xlsx'); 
    */

    require_once __DIR__ . '/classes/php2excel.php';
    $baseHeaders = $cheaders;
    // Περνάμε το index 6 (γιατί ο 'Αριθμ. Πρωτ.' είναι το 7ο στοιχείο, ξεκινώντας από το 0)
    $php2excel = new PhpToExcel($baseHeaders, $datas, 6);
    $php2excel->create('./uploads/app104.xlsx');    
    
    //return 'Excel app104.xlsx Created <br>'.$row_counter.' rows exported <br> <a href="./uploads/app104.xlsx" download>Λήψη excel Δηλώσεων για Τοποθέτηση & Συμπλήρωση Ωραρίου Εκπαιδευτικών στην Διάθεση του ΠΥΣΔΕ Φλώρινας ή ΑΠΟΣΠΑΣΜΕΝΩΝ από άλλα ΠΥΣΔΕ</a><br>';
    return 'Excel app104.xlsx Created <br>'.$row_counter.' rows exported <br>  <a href="dl.php?f=app104.xlsx&dir=main" class="btn btn-primary">Λήψη excel</a> <br>';
}

function show_managers_applications()
{
    global $srv_conf;
    
    $html2return='
    <div class="container-fluid mt-4 mb-5">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-warning text-dark py-3">
                <h4 class="mb-0 fw-bold"><i class="bi bi-inbox me-2"></i>Αιτήσεις προς Πρωτοκόλληση</h4>
            </div>
            <div class="card-body p-4 table-responsive">
    ';

    $html2return_applications_table='
        <table id="pending_applications_table" class="table table-striped table-hover table-bordered align-middle w-100">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width: 60px;">Α/Α</th>
                    <th>Ονοματεπώνυμο</th>
                    <th>Θέμα Αίτησης</th>
                    <th class="text-center" style="width: 180px;">Ημερομηνία Υποβολής</th>
                    <th class="text-center" style="width: 120px;">Ενέργειες</th>
                </tr>
            </thead>
            <tbody>
    ';

    $aa=1;
    $sql="SELECT * FROM APPLICATIONS WHERE PROTOCOL_ID=0 ";
    $result=dbq($sql);
    $tbody='';

    while($row=mysqli_fetch_array($result))
    {
        $date=date("d/m/Y H:i:s",json_decode($row['DATAS'],true)['time_last_modified']);
        $onclick='onclick="show_managers_permanent_application(\''.$row['AFM'].'\',\''.$row['ID'].'\');" style="cursor:pointer;"';
        
        if($row['AFM']!='')
        {
            $userdata=afm_data($row['AFM']);
            $tbody_row='
            <tr>
                <td class="text-center fw-bold text-muted" '.$onclick.'>
                    '.$aa++.'
                </td>
                <td '.$onclick.'>
                    <div class="fw-bold text-primary">'.$userdata['fullname'].'</div>
                    <div class="small text-muted"><i class="bi bi-person-vcard me-1"></i>ΑΦΜ: '.$row['AFM'].'</div>
                </td>
                <td '.$onclick.'>
                    '.$srv_conf['applications_permanent'][$row['CATEGORY']].'
                </td>
                <td class="text-center" '.$onclick.'>
                    <span class="badge bg-info text-dark fs-6 shadow-sm">'.$date.'</span>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary shadow-sm" onclick="sendmail_protocol2application(\''.$row['ID'].'\');" title="Αποστολή email στην Γραμματεία">
                        <i class="bi bi-envelope-paper"></i> Mail
                    </button>
                </td>
            </tr>
            ';  
            $tbody.=$tbody_row;
        }
    }
    
    $html2return_applications_table .= $tbody;

    if($aa==1)
    {
        // Μήνυμα επιτυχίας όταν δεν υπάρχουν εκκρεμότητες!
        $html2return_applications_table='
            <div class="alert alert-success text-center my-4 fs-5 shadow-sm border-0">
                <i class="bi bi-check-circle-fill me-2 fs-3 align-middle"></i> 
                Δεν υπάρχουν εκκρεμείς αιτήσεις προς πρωτοκόλληση. Όλα είναι εντάξει!
            </div>
        ';
    }
    else
    {
        $html2return_applications_table.='
                </tbody>
            </table>';
    }

    $html2return2='
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        var element = document.getElementById("pending_applications_table");
        if(typeof(element) != "undefined" && element != null) {
            
            if ($.fn.DataTable.isDataTable("#pending_applications_table")) {
                $("#pending_applications_table").DataTable().destroy();
            }
            
            $("#pending_applications_table").DataTable({
                paging: true,
                pageLength: 30,
                lengthMenu: [
                    [30, 50, 80, -1],
                    [30, 50, 80, "Όλες"]
                ],
                order: [[ 0, "desc" ]], // Τα πιο πρόσφατα πάνω
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/el.json"
                },
                dom: "<\'row mb-3\'<\'col-sm-12 col-md-6\'l><\'col-sm-12 col-md-6\'f>>" +
                     "<\'row\'<\'col-sm-12\'tr>>" +
                     "<\'row mt-3\'<\'col-sm-12 col-md-5\'i><\'col-sm-12 col-md-7\'p>>",
                buttons: [\'excelHtml5\']
            });
        }
    });
    </script>
    ';

    return $html2return.$html2return_applications_table.$html2return2;
}

function show_managers_applications_deprecated()
{
    global $srv_conf;
    $html2return='
    <div id="usersdata" class="container-fluid " style="text-align:center;">
    <br>
    <div style="margin:auto auto;padding:10px;background-color:cyan;border:1px solid yellow;">
        <h2> Αιτήσεις Χρηστών προς Πρωτοκόλληση </h2>
        <br>';

    $html2return_applications_table='
            <table class="change_background" style="margin: auto auto;border: 2px solid grey;padding: 10px;">
                <thead>
                    <tr style="background:darkblue;color:white;">
                        <th style="text-align:center;">
                            AA    
                        </th>
                        <th style="text-align:center;">
                            Ονοματεπώνυμο
                        </th>
                        <th style="text-align:center;">
                            Θέμα
                        </th>
                        <th style="text-align:center;">
                            Υμερομηνία Υποβολής
                        </th>
                        <th style="text-align:center;font-size:85%;">
                            Mail
                        </th>
                    </tr>
                </thead)
                <tbody>
                ';

    $aa=1;
    $sql="SELECT * FROM APPLICATIONS WHERE PROTOCOL_ID=0 ";
    $result=dbq($sql);
    while($row=mysqli_fetch_array($result))
    {
        $date=date("d/m/Y H:i:s",json_decode($row['DATAS'],true)['time_last_modified']);
        $onclick='onclick="show_managers_permanent_application(\''.$row['AFM'].'\',\''.$row['ID'].'\',);"';
        if($row['AFM']!='')
        {
            $userdata=afm_data($row['AFM']);
            $html2return_applications_table.='
            <tr >
                <td style="text-align:center;cursor:pointer;" '.$onclick.'>
                    '.$aa++.'
                </td>
                <td '.$onclick.' style="cursor:pointer;">
                '.$userdata['fullname'].'
                </td>
                <td '.$onclick.' style="cursor:pointer;">
                '.$srv_conf['applications_permanent'][$row['CATEGORY']].'
                </td>
                <td style="text-align:center;cursor:pointer;" '.$onclick.'>
                '.$date.'
                </td>
                <td style="text-align:center;cursor:pointer;">
                    <button name="sendmail" onclick="sendmail_protocol2application(\''.$row['ID'].'\');" title="Αποστολή email στην Γραμματεία"> Mail </button>
                </td>

            </tr>
            ';  
        }

    }
    if($aa==1)
        {
            $html2return_applications_table='
                <p style="text-align:center;font-style:italic;">
                    Δεν υπάρχουν Αιτήσεις προς Πρωτοκόλληση
                </p>
                ';
        }


    $html2return_applications_table.='
            </tbody>
        </table>
        <br><br><br>
    </div>
    </div>
    ';

    return $html2return.$html2return_applications_table;
}

function manager_connect2user($afm)
{
    global $htmlout;

    if($afm!='')
    {
        $sql="SELECT * FROM USERS WHERE AFM=$afm";
        $result=dbq($sql);
        $usersdatas=mysqli_fetch_array($result);
        //$usersdatas['JOB_STATUS']=$sql;
        //$userdatas['MOBILE']=count($usersdatas);
    }
    else
    {
        $usersdatas='function problem';
    }

    logdata('Σύνδεση MANAGER στον Χρήστη ΑΦΜ: '.afmed($usersdatas['AFM']).' '.$usersdatas['SNAME'].' '.$usersdatas['FNAME']);
    session_start();
    $_SESSION['userdatas']=$usersdatas;
    $_SESSION['manager_or_secretatry_connected2user']=1;
    session_write_close();

    $father=mb_substr($usersdatas['FANAME'],0,3);
    
    $navbar_user_title=
        $usersdatas['SNAME'].' '.$father.' '.$usersdatas['FNAME'].' 
            <small><small><small><small> <br>- σύνδεση Διαχειριστή - </small></small></small></small>';
    
    $return_datas=array('navbar'=>$htmlout->navbar($navbar_user_title,1),$usersdatas);

    return json_encode($return_datas);

}

function manager_return()
{
    global $htmlout;
    session_start();
    unset($_SESSION['userdatas']);
    unset($_SESSION['manager_or_secretatry_connected2user']);
    //unset($_SESSION['sch_datas']);
    $usertype=$_SESSION['usertype'];
    session_write_close();
    $usertype=='manager'?$usertype_access_name='Manager':$usertype_access_name='Secretary';
    $return_datas=array('navbar'=>$htmlout->navbar($usertype_access_name,0));

    return json_encode($return_datas,TRUE);

}

function show_logs_page()
{
    $logfiles=scandir('./logs');
    unset($logfiles[0],$logfiles[1]); // Αφαίρεση των '.' και '..'
    
    $html2return='
    <div class="container-fluid mt-4 mb-5">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                <h4 class="mb-0 fw-bold"><i class="bi bi-terminal me-2"></i>Αρχεία Καταγραφής (Logs)</h4>
                <i class="bi bi-journal-code fs-4 text-white-50"></i>
            </div>
            <div class="card-body p-4 bg-light">
                
                <div class="row mb-4 justify-content-center">
                    <div class="col-md-6 col-lg-4 text-center">
                        <label for="log_files" class="form-label fw-bold text-muted small text-uppercase">Επιλογή Αρχείου Καταγραφής</label>
                        <select id="log_files" class="form-select form-select-lg shadow-sm border-secondary text-center fw-semibold" onchange="show_selected_logfile();">';
                        
        $count_log_files=0;
        foreach($logfiles as $file)
        {
            $count_log_files++;
            $selected = ($count_log_files == count($logfiles)) ? ' selected ' : '';
            $html2return.='<option value="'.$file.'" '.$selected.'>📄 '.$file.'</option>';
        }

    $content=read_log_file(end($logfiles));

    $html2return.=' 
                        </select>
                    </div>
                </div>
                
                <div class="log-container position-relative">
                    <label class="form-label fw-bold text-muted small text-uppercase mb-2"><i class="bi bi-list-columns-reverse me-1"></i>Περιεχόμενο Αρχείου</label>
                    <textarea id="log_file_data" class="form-control font-monospace bg-dark text-info p-4 rounded-3 shadow-inner" rows="22" readonly style="font-size: 0.85rem; resize: vertical; line-height: 1.6;">'.$content.'</textarea>
                </div>
                
            </div>
        </div>
    </div>';
    
    return $html2return;
}


function show_logs_page_deprecated()
{
    

    $logfiles=scandir('./logs');
    unset($logfiles[0],$logfiles[1]);
    $html2return='
        <div style="width:100%;text-align:center;">
        <br><br>
            <table style="margin:auto auto;">
                <tr>
                    <td style="text-align:center">
                        <select id="log_files" onchange="show_selected_logfile();">';
        $count_log_files=0;
        foreach($logfiles as $file)
        {
            $count_log_files++;
            if($count_log_files==count($logfiles)){$selected=' selected ';}else{$selected='';}
            $html2return.='<option value="'.$file.'" '.$selected.'">'.$file.'.</option>';
        }


    $content=read_log_file(end($logfiles));

    $html2return.=' 
                        </select>
                    </td>
                </tr>
                <tr>
                    <td style="text-align:center;">
                        <textarea id="log_file_data" rows="30" cols="240" disabled style="text-align:left;font-size:70%;">
                            '.$content.'
                        </textarea>
                    </td>
                </tr>
            </table>
        <br><br>
        </div>';
    return $html2return;
}


function read_log_file($logfile)
{
    $content=file_get_contents('./logs/'.$logfile);
    $content_as_array=explode("\n",$content);
    $content_as_array_reversed=array_reverse($content_as_array);
    $reversed_content='';
    foreach($content_as_array_reversed as $ln)
    {
        $reversed_content.=$ln."\n";
    }
    return $reversed_content;
}


function new_permanent_application($id, $db_application_id = 0)
{
    global $srv_conf;
    $applyform_width = $srv_conf['applyform_width'];
    session_start();
    $usersdatas = $_SESSION['userdatas'];
    $usertype = $_SESSION['usertype'];
    session_write_close();
    $app_record_found = 1;

    if ($db_application_id != 0) {
        $sql = "SELECT * FROM APPLICATIONS WHERE ID=$db_application_id ";
        $result = dbq($sql);
        $row = mysqli_fetch_array($result);
        if (!(isset($row['ID']))) { $app_record_found = 0; }
        $saved_datas[$id] = json_decode($row['DATAS'], true);
        $afm = $row['AFM'];
        $protocol_id = $row['PROTOCOL_ID'];
        $protocol_date = $row['PROTOCOL_DATE'];
    } else {
        $saved_datas = load_permanent_applications_data();
        $afm = $usersdatas['AFM'];
        $protocol_id = 0;
        $protocol_date = '';
    }

    $final = 0;

    // --- BUTTONS SETUP ---
    if (app_intime($id)) {
        $buttons_table = '
        <div class="d-flex justify-content-center gap-2 mt-4 mb-5">
            <button id="save_application" class="btn btn-outline-primary shadow-sm" onclick="get_form_data(0);">
                <i class="bi bi-save"></i> Αποθήκευση
            </button>
            <button id="submit_application" class="btn btn-success shadow-sm" onclick="submit_permenet_application();">
                <i class="bi bi-send-check"></i> Υποβολή
            </button>
            <button id="return2permenet_application_menu" class="btn btn-secondary shadow-sm" onclick="new_applications();">
                <i class="bi bi-arrow-left"></i> Επιστροφή
            </button>
            <button id="delete_application" class="btn btn-outline-danger shadow-sm" onclick="delete_permanent_application_data(' . $id . ',0,\'' . $afm . '\');" style="display:none;">
                <i class="bi bi-trash"></i> Διαγραφή
            </button>
        </div>';
    } else {
        $buttons_table = '
        <div class="d-flex justify-content-center mt-4 mb-5">
            <button id="return2permenet_application_menu" class="btn btn-secondary shadow-sm" onclick="new_applications();">
                <i class="bi bi-arrow-left"></i> Επιστροφή
            </button>
        </div>';
    }

    if (isset($saved_datas[$id]['FINAL'])) {
        $time_created = $saved_datas[$id]['time_created'];
        $final = ($saved_datas[$id]['FINAL']);
    } else {
        $time_created = time();
    }

    if ($final == 1) {
        if (($db_application_id != 0) && ($usertype != 'user')&& (!isset($_SESSION['manager_or_secretatry_connected2user']))) {
            if (($protocol_id == 0) ) {
                $buttons_table = '
                <div class="d-flex justify-content-center gap-2 mt-4 mb-5">
                    <button class="btn btn-primary shadow-sm" onclick="assign_protocol2permanent_application(' . $id . ',' . $db_application_id . ');">
                        <i class="bi bi-file-earmark-check"></i> Πρωτοκόλληση 
                    </button>
                    <button class="btn btn-secondary shadow-sm" onclick="show_managers_applications();">
                        <i class="bi bi-arrow-left"></i> Επιστροφή
                    </button>
                    <button class="btn btn-outline-danger shadow-sm" onclick="delete_permanent_application_data(' . $id . ',' . $db_application_id . ',\'' . $afm . '\');">
                        <i class="bi bi-trash"></i> Διαγραφή
                    </button>
                </div>';
                $protocol_infos = '<div id="protocol_assigment" class="text-end" style="font-size: 80%;">';
                $protocol_infos .= 'Αρ. Πρωτοκόλλου: <input class="protocoldata form-control d-inline-block w-auto" type="number" name="PROTOCOL_ID"><br>';
                $protocol_infos .= 'Ημερομηνία: <input class="protocoldata form-control d-inline-block w-auto" type="date" name="PROTOCOL_DATE" value="' . date('Y-m-d') . '"></div>';
            } else {
                $buttons_table = '
                <div class="d-flex justify-content-center gap-2 mt-4 mb-5">
                    <form action="./download.php" method="post" class="m-0">
                        <input type="hidden" name="afm" value="' . $afm . '">
                        <input type="hidden" name="db_application_id" value="' . $db_application_id . '">
                        <button class="btn btn-primary shadow-sm" type="submit"><i class="bi bi-file-pdf"></i> Λήψη PDF</button>
                    </form>
                    <button class="btn btn-secondary shadow-sm" onclick="show_managers_history();"><i class="bi bi-arrow-left"></i> Επιστροφή</button>
                    <button class="btn btn-outline-danger shadow-sm" onclick="delete_permanent_application_protocol(' . $db_application_id . ');"><i class="bi bi-trash"></i> Διαγραφή Πρωτοκόλλου</button>
                </div>';
                $protocol_infos = '<div id="protocol_assigment" class="text-end" style="font-size: 80%;">';
                $protocol_infos .= 'Αρ. Πρωτοκόλλου: <strong>' . $protocol_id . '</strong><br>Ημερομηνία: <strong>' . $protocol_date . '</strong></div>';
            }
        } else {
            // VIEW MODE για User
            $get_pdf_button='';
            if($protocol_id != 0)
                {
                    $get_pdf_button='
                    <form action="./download.php" method="post" class="m-0">
                        <input type="hidden" name="afm" value="' . $afm . '">
                        <input type="hidden" name="db_application_id" value="' . $db_application_id . '">
                        <button class="btn btn-primary shadow-sm" type="submit"><i class="bi bi-file-pdf"></i> Λήψη PDF</button>
                    </form>';
                    
                }
            $buttons_table = '
            <div class="d-flex justify-content-center gap-2 mt-4 mb-5">
                '.$get_pdf_button.'
                <button class="btn btn-secondary shadow-sm" onclick="applications_history();">
                    <i class="bi bi-arrow-left"></i> Επιστροφή στο Ιστορικό
                </button>
            </div>';
            
            $protocol_infos = ($protocol_id == 0) ? '<p class="fst-italic">Η αίτηση αναμένει πρωτοκόλληση.</p>' : 
                              '<div class="text-end">Αρ. Πρωτοκόλλου: <strong>' . $protocol_id . '/' . formdate($protocol_date) . '</strong></div>';
        }

        $app_status = 'Η Αίτηση υποβλήθηκε Ηλεκτρονικά<br>' . date('d/m/Y - H:i:s', $saved_datas[$id]['time_last_modified']) . '<br>' . $protocol_infos;
    } else {
        $app_status = ($app_record_found == 0) ? 'Δεν βρέθηκε η αίτηση' : 'Νέα Αίτηση';
    }

    // --- HTML GENERATION ---
    $html2return = '<div class="container py-4">';
    if (!(app_intime($id)) && ($final != 1)) {
        $html2return .= '<div class="text-center"><h2>Η αίτηση είναι εκτός ορίων.</h2>' . $buttons_table . '</div>';
    } else {
        $html2return .= '
            <div class="text-center mb-4"><h2>' . $app_status . '</h2></div>
            <div class="card shadow-sm border-0 p-4">';
        if(!($app_status=='Δεν βρέθηκε η αίτηση'))
            {
                $html2return .= '<input class="appdata" name="application_id" value="' . $id . '" disabled style="display:none;">';
                $html2return .= '<input id="final_appdata" name="FINAL" value="' . $final . '" disabled style="display:none;">';
                $html2return .= '<div class="row"><div class="col-md-6">' . show_usersdata_table($usersdatas, 0) . '</div>';
                $html2return .= '<div class="col-md-6">' . permenent_application_contnent($id, $db_application_id) . '</div></div>';
                $html2return .= $buttons_table . '</div>';
            }

    }
    $html2return .= '</div>';

    return json_encode([$html2return, $saved_datas[$id]]);
}

function new_permanent_application_deprecated($id,$db_application_id=0)
{
    global $srv_conf;
    //$applyform_width=$srv_conf['applyform_width'];
    $applyform_width=960;
    session_start();
    $usersdatas=$_SESSION['userdatas'];
    $usertype=$_SESSION['usertype'];
    session_write_close();
    $app_record_found=1; // turns to 0 if there is no record in database for this application id


if($db_application_id!=0)
{
    $sql="SELECT * FROM APPLICATIONS WHERE ID=$db_application_id ";
    $result=dbq($sql);
    $row=mysqli_fetch_array($result);
    if(!(isset($row['ID']))){$app_record_found=0;}
    $saved_datas[$id]=json_decode($row['DATAS'],true);
    $afm=$row['AFM'];
    $protocol_id=$row['PROTOCOL_ID'];
    $protocol_date=$row['PROTOCOL_DATE'];
}
else
{
    $saved_datas=load_permanent_applications_data();
    $afm=$usersdatas['AFM'];
    $protocol_id=0;
    $protocol_date='';
}


    $final=0;


    if(app_intime($id))
    {

        $buttons_table = '
        <div class="d-flex justify-content-center gap-2 mt-4 mb-5">
            <button id="save_application" class="btn btn-outline-primary shadow-sm" onclick="get_form_data(0);">
                <i class="bi bi-save"></i> Αποθήκευση
            </button>
            <button id="submit_application" class="btn btn-success shadow-sm" onclick="submit_permenet_application();">
                <i class="bi bi-send-check"></i> Υποβολή
            </button>
            <button id="return2permenet_application_menu" class="btn btn-secondary shadow-sm" onclick="new_applications();">
                <i class="bi bi-arrow-left"></i> Επιστροφή
            </button>
            <button id="delete_application" class="btn btn-outline-danger shadow-sm" onclick="delete_permanent_application_data('.$id.',0,\''.$afm.'\');" style="display:none;">
                <i class="bi bi-trash"></i> Διαγραφή
            </button>
        </div>';
    }
    else
    {
        $buttons_table='
        <table style="width: '.$applyform_width.'px;border:collapse;margin:auto auto;">
        <tbody>
            <tr>
                <td id="return2permenet_application_menu" style="text-align:center;">
                    <button  class="button01" onclick="new_applications();">Επιστροφή</button>
                </td>
            </td>
            </tr>
        </tbody>
        </table>
    ';
    }


    if(isset($saved_datas[$id]['FINAL'])) // appliction is saved or submited
        {
            $time_created=$saved_datas[$id]['time_created'];
            $final=($saved_datas[$id]['FINAL']);
        }
        else
        {
            $time_created=time();
        }
    if($final==1) // application is submited
    {
            if(($db_application_id!=0)&&($usertype!='user'))
            {
                if($protocol_id==0)
                {
                    $buttons_table='
                    <table style="width: '.$applyform_width.'px;border:collapse;margin:auto auto;">
                    <tbody>
                        <tr>
                            <td id="return2permenet_application_menu" style="text-align:center;">
                                <button  class="button01" onclick="assign_protocol2permanent_application('.$id.','.$db_application_id.');">Πρωτοκόλληση</button>
                            </td>
                            <td id="return2permenet_application_menu" style="text-align:center;">
                                <button  class="button01" onclick="show_managers_applications();">Επιστροφή </button>
                            </td>
                            <td id="delete_application" style="text-align:center;">
                            <button  class="button01" onclick="delete_permanent_application_data('.$id.','.$db_application_id.',\''.$afm.'\');">Διαγραφή</button>
                        </td>
                        </tr>
                    </tbody>
                    </table>
                    
                    ';
                    $protocol_infos='
                    <div id="protocol_assigment" style="margin:auto auto;width: '.$applyform_width.'px;background-color:F5F5F5;text-align:right;font-size:60%;">
                    Αριθμός Πρωτοκόλλου: <input class="protocoldata" type="number" name="PROTOCOL_ID" style="width:125px;">
                    <br>
                    Ημερομηνία: <input class="protocoldata" type="date" name="PROTOCOL_DATE" value="'.date('Y-m-d').'" style="width:125px;">
                    </div>
                    ';
                }
                else
                {
                    $buttons_table='
                    <table style="width: '.$applyform_width.'px;border:collapse;margin:auto auto;">
                    <tbody>
                        <tr>
                            <td id="download_applications_pdf" style="text-align:center;">
                                <form action="./download.php" method="post">
                                    <input type="text" name="afm" value="'.$afm.'" style="display:none;">
                                    <input type="text" name="db_application_id" value="'.$db_application_id.'" style="display:none;">
                                    <button  class="button01" type="submit" value="Submit">Λήψη PDF</button>
                                </form>
                            </td>
                            <td id="return2permenet_application_menu" style="text-align:center;">
                                <button  class="button01" onclick="show_managers_history();">Επιστροφή</button>
                            </td>
                            <td id="delete_application" style="text-align:center;">
                            <button  class="button01" onclick="delete_permanent_application_protocol('.$db_application_id.');">Διαγραφή Πρωτοκόλλου</button>
                        </td>
                        </tr>
                    </tbody>
                    </table>
                    ';
                    $protocol_infos='
                    <div id="protocol_assigment" style="margin:auto auto;width: '.$applyform_width.'px;background-color:F5F5F5;text-align:right;font-size:60%;">
                        Αριθμός Πρωτοκόλλου: <input class="protocoldata" type="number" name="PROTOCOL_ID" style="width:125px;" value="'.$protocol_id.'" disabled>
                        <br>
                        Ημερομηνία: <input class="protocoldata" type="date" name="PROTOCOL_DATE" value="'.$protocol_date.'" style="width:125px;" value="'.$protocol_date.'" disabled>
                    </div>
                ';

                }
                
            }
            else
            {
                
                if($protocol_id==0)
                {    
                    $protocol_infos='
                    <p style="font-size:60%;font-style:italic;">όταν πρωτοκολληθεί θα την βρείτε στο "Ιστορικό" σας.</p>
                    ';
                    $buttons_table='
                    <table style="width: '.$applyform_width.'px;border:collapse;margin:auto auto;">
                    <tbody>
                        <tr>
                            <td id="return2permenet_application_menu" style="text-align:center;">
                                <button  class="button01" onclick="new_applications();">Επιστροφή</button>
                            </td>
                        </td>
                        </tr>
                    </tbody>
                    </table>
                ';
                }
                else
                {
                    $protocol_infos='
                    <div id="protocol_assigment" style="margin:auto auto;width: '.$applyform_width.'px;background-color:F5F5F5;text-align:right;font-size:60%;">
                        Αριθμός Πρωτοκόλλου: <input class="protocoldata" type="number" name="PROTOCOL_ID" style="width:125px;" value="'.$protocol_id.'" disabled>
                        <br>
                        Ημερομηνία: <input class="protocoldata" type="date" name="PROTOCOL_DATE"  style="width:125px;" value="'.$protocol_date.'" disabled>
                    </div>
                    ';  
                    $buttons_table='
                    <div class="d-flex justify-content-center gap-2 mt-4 mb-5">
                        <form action="./download.php" method="post" class="m-0">
                            <input type="hidden" name="afm" value="'.$afm.'">
                            <input type="hidden" name="db_application_id" value="'.$db_application_id.'">
                            <button class="btn btn-primary shadow-sm" type="submit">
                                <i class="bi bi-file-pdf"></i> Λήψη PDF
                            </button>
                        </form>
                        <button id="return2permenet_application_menu" class="btn btn-secondary shadow-sm" onclick="applications_history();">
                            <i class="bi bi-arrow-left"></i> Επιστροφή στο Ιστορικό
                        </button>
                    </div>
                    '; 
                }
            }


            $app_status='
            Η Αίτηση υποβλήθηκε Ηλέκτρονικά<br>
             '.date('d/m/Y - H:i:s',$saved_datas[$id]['time_last_modified']).$protocol_infos;
             
    }
    else
    {
        if($app_record_found==0)
            {$app_status='Δεν βρέθηκε η αίτηση στην Database<br>';}
        else{
            $app_status='Νέα αίτηση
                    <br><section style="width:60%;word-wrap: normal;font-size:45%;color:red;margin:auto auto;">
                        '.$srv_conf['applications_comments'][$id].'
                    </section>';
            }
    }

    if((!(app_intime($id)))&&($final!=1))
    {
        $starts=date("H:i - d/m/Y",date2time_timestamp($srv_conf['time_limits'][$id]['starts']));
        $ends=date("H:i - d/m/Y",date2time_timestamp($srv_conf['time_limits'][$id]['ends']));
        $html2return='
        <br><br>
        <section style="text-align:center;">
            <h2>Η αίτηση είναι ενεργή για υποβολή στο διάστημα</h2>
            <h3 style="color:red;">
            από '.$starts.' <br> ως '.$ends.'
            </h3>
            <br>
            '.$buttons_table.'
        </section>
        
        ';
    }
    else
    {
        if($app_record_found==0)
            {            
                $html2return='
                <br><br>
                <section style="text-align:center;">
                    <h2>'.$app_status.'</h2>
                    <br>
                </section>}';
            }
        else{
            $html2return='
                <br><br>
                <section style="text-align:center;">
                    <h2>'.$app_status.'</h2>
                    <br>
                </section>
                <div style="width:95%;margin: auto auto;text-align:center;">
                    <input class="appdata" name="application_id" value="'.$id.'" disabled style="display:none;">
                    <input class="appdata" name="time_created" value="'.$time_created.'" disabled style="display:none;">
                    <input class="appdata" name="time_last_modified" value="'.time().'" disabled style="display:none;">
                    <input id="final_appdata" name="FINAL" value="'.$final.'" disabled style="display:none;">

                    <table style="width: '.$applyform_width.'px;border:1px solid black;margin:auto auto;">
                        <tbody>
                            <tr>
                                <td style="width:50%; text-align:center;font-weight:bold;font-size:120%;">
                                '.$srv_conf['applications_permanent'][$id].'
                                </td>
                                <td style="width:50%; text-align:center;font-weight:bold;font-size:120%;">
                                ΠΡΟΣ '.$srv_conf['Main Configuration']['Apply to'].'
                                </td>
                            <tr>
                                <td colspan="2">
                                <br>
                                </td>
                            </tr>
                            <tr>
                                <td >
                                '.show_usersdata_table($usersdatas,0).'
                                </td>
                                <td style="font-weight:bold; text-align: justify;text-justify: inter-word;">
                                    '.permenent_application_contnent($id,$db_application_id).'
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <br>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <br>
                    '.$buttons_table.'
                </div>
                <br>
                <br>
                <br>
                <br><br><br><br>
                ';
        } // end else if database record not found
    }
    
    $json_html2return=array($html2return,$saved_datas[$id]);
    return json_encode($json_html2return);
}

function restore_thema()
{
    global $con;
    $sql="SELECT * FROM APPLICATIONS";
    $result=dbq($sql);
    $count_themas=0;
    $error_themas=array();
    while($row=mysqli_fetch_array($result))
        {
            $appdatas=json_decode($row['DATAS'],true);    
            if(isset($appdatas['thema type=']))
                {
                    $db_application_id=$row['ID'];
                    $count_themas++;
                    //$error_themas[$db_application_id]=$appdatas;
                    $appdatas['thema']=$appdatas['thema type='];
                    unset($appdatas['thema type=']);
                    $error_themas[$db_application_id]=$appdatas;
                    $jdatas=json_encode($appdatas, JSON_UNESCAPED_UNICODE);
                    $jdatas = mysqli_real_escape_string($con, $jdatas);
                    $sql="UPDATE APPLICATIONS SET DATAS='$jdatas' WHERE ID=$db_application_id";
                    //$result1=dbq($sql);
                }
        }
    return $count_themas.'<br>'.show_array($error_themas);
}

function permenent_application_contnent($id,$db_application_id,$pdf=0)
{
    global $htmlout;
    $appdatas=array();
    if($db_application_id)
    {
        $sql="SELECT * FROM APPLICATIONS WHERE ID='$db_application_id'";
        $result=dbq($sql);
        $row=mysqli_fetch_array($result);
        $appdatas=json_decode($row['DATAS'],true);
    }
    else
    {
        $datas_in_userfile=load_permanent_applications_data();
        (isset($datas_in_userfile[$id]))?$appdatas=$datas_in_userfile[$id]:$appdatas=array();
    }

    $contents=$htmlout->appl_entries($id,$appdatas,$pdf);
    if(isset($appdatas['FINAL'])){$app_is_final=$appdatas['FINAL'];}else{$app_is_final=0;}
    $html2return=$contents['html'].'<br><input style="display:none;" id="app_is_final" type="text" value="'.$app_is_final.'">';
    $files_attached=$contents['files_attached'];


        if(count($files_attached))
        {
            // DELETE uploaded files if it new application
            $datas=load_permanent_applications_data();
            if(!(isset($datas[$id])))
            {
                foreach($files_attached as $file2delete=>$title)
                    {
                        delete_file($file2delete);
                    }
            }

            $html2return.=files_attached($files_attached,0,$db_application_id);
        }

    // Πριν το return στο τέλος της permenent_application_contnent
    //$html2return=$contents['html'].'<br><input style="display:none;" id="app_is_final" type="text" value="'.$app_is_final.'">';
    return $html2return;
}


function files_attached($fileslist,$afm=0,$db_application_id=0)
{
    if($afm==0)
    {
        session_start();
        $usersdatas=$_SESSION['userdatas'];
        session_write_close();   
        $afm=$usersdatas['AFM'];
    }

    $html2return='<br><br> Συνημμένα υποβάλω: <br>';
    $aa=1;
    foreach($fileslist as $filename=>$title)
    {
        
        if(check_if_file_exist($filename)==1)
        {
            $display_delete_button='';
        }
        else
        {
            $display_delete_button=' style="display:none;"';
        }

        $html2return.='
            <section id="'.$filename.'_section">
                <a onclick="manage_upload_or_download_file(\''.$filename.'\');return false;" style="fonr-style:italic; color:blue;cursor: pointer" title="Παρακαλώ επιλέξτε για μεταφόρτωση Αρχείου">
                    '.$aa++.') '.$title.' 
                </a>
                <img class="Delete_Button" id="'.$filename.'_deletebutton" src="./images/delete_file.png" width="24px" onclick="delete_file(this);" '.$display_delete_button.' / title="Διαγραφή Αρχείου">
            </section>
            <section id="'.$filename.'_status" style="color:red;width:100%;text-align:center;">

            </section>
            <section id="'.$filename.'_manage_section">
            <input id="'.$filename.'_data" class="appdata" type="text" name="file_'.$filename.'" placeholder="uploaded_file" style="display:none;">
            <input type="file" id="'.$filename.'" name="'.$filename.'" onchange="upload_file(this);" style="display:none;">
            <a id="'.$filename.'_download" href="dl.php?f='.$afm.'_'.$filename.'&dir=main"></a>
            </section>
            ';
    }

    if($db_application_id)
    {
        $html2return='';
        $aa=1;
        $sql="SELECT DATAS FROM APPLICATIONS WHERE ID=$db_application_id";
        $result=dbq($sql);
        $row=mysqli_fetch_array($result);
        $db_datas=json_decode($row['DATAS'],true);
        foreach($fileslist as $filename=>$title)
        {
            $original_filename=$db_datas['file_'.$filename];
            if(check_if_file_exist('./applications/uploads/'.$db_application_id.'_'.$filename,0))
            {
                /*
                $link2file='
                <a href="./applications/uploads/'.$db_application_id.'_'.$filename.'" download="'.$original_filename.'" style="font-style:italic; color:blue;cursor:pointer;" title="'.$original_filename.'">
                '.$aa++.') '.$title.' 
                </a>
                ';
                */
                $link2file = '
                    <a href="dl.php?f=' . $db_application_id . '_' . $filename . '&n=' . urlencode($original_filename) . '&dir=app" style="font-style:italic; color:blue;cursor:pointer;" title="' . $original_filename . '">
                        ' . $aa++ . ') ' . $title . ' 
                    </a>
                    ';
            }
            else
            {
                $link2file='
                <section style="color:red;">
                    '.$aa++.') '.$title.' - ΔΕΝ Υποβλήθηκε
                </section>
                ';
            }
            $html2return.='
            <section id="'.$filename.'_section" style="text-align:left;">
            '.$link2file.'
            </section>';
        }
        if($aa>1)
        {
            $html2return='<br><br> Συνημμένα υποβάλω: <br>'.$html2return;
        }
    }

    return $html2return;
}

function check_if_file_exist($filename,$use_prefix_afm=1,$afm=0)
{
    if($use_prefix_afm)
    {
        if($afm==0)
        {
            session_start();
            $usersdatas=$_SESSION['userdatas'];
            session_write_close();   
            $afm=$usersdatas['AFM'];
        }

        $filename='uploads/'.$afm.'_'.$filename;

    } 

    if(file_exists($filename))
        {return 1;}
    else
        {return 0;}
}

function isValidIBAN ($iban) {

    $banks=array(
        '010' => 'ΤΡΑΠΕΖΑ ΤΗΣ ΕΛΛΑ∆ΟΣ Α.Ε.',
        '011' => 'ΕΘΝΙΚΗ ΤΡΑΠΕΖΑ ΤΗΣ ΕΛΛΑ∆ΟΣ Α.Ε.', 
        '012' => 'ΕΜΠΟΡΙΚΗ ΤΡΑΠΕΖΑ ΤΗΣ ΕΛΛΑΔΟΣ Α.Ε.',
        '014' => 'ALPHA BANK', 
        '015' => 'ΓΕΝΙΚΗ ΤΡΑΠΕΖΑ ΤΗΣ ΕΛΛΑΔΟΣ Α.Ε.',
        '016' => 'ATTICA BANK',
        '017' => 'ΤΡΑΠΕΖΑ ΠΕΙΡΑΙΩΣ Α.Ε.',
        '026' => 'EUROBANK ERGASIAS Α.Ε.',
        '028' => 'ΕΓΝΑΤΙΑ ΤΡΑΠΕΖΑ Α.Ε.',
        '031' => 'ΛΑΪΚΗ ΤΡΑΠΕΖΑ (ΕΛΛΑΣ) Α.Ε.', 
        '034' => 'ΕΠΕΝ∆ΥΤΙΚΗ ΤΡΑΠΕΖΑ ΕΛΛΑ∆ΟΣ ΑΕ',
        '037' => 'ΩΜΕΓΑ ΤΡΑΠΕΖΑ Α.Ε.',
        '038' => 'ΤΡΑΠΕΖΑ NOVABANK Α.Ε.',
        '039' => 'BNP PARIBAS Security Service',
        '040' => 'FGA BANK G.M.B.H.',
        '043' => 'ΑΓΡΟΤΙΚΗ ΤΡΑΠΕΖΑ ΤΗΣ ΕΛΛΑΔΟΣ',
        '049' => 'ΠΑΝΕΛΛΗΝΙΑ ΤΡΑΠΕΖΑ Α.Ε.',
        '050' => 'ΤΡΑΠΕΖΑ ΣΑΝΤΕΡΑΤ ΙΡΑΝ',
        '053' => 'PROTON ΕΠΕΝΔΥΤΙΚΗ ΤΡΑΠΕΖΑ Α.Ε.',
        '054' => 'ΤΡΑΠΕΖΑ PROBANK A.E.',
        '056' => 'AEGEAN BALTIC BANK Α.Τ.Ε.', 
        '057' => 'CREDICOM CONSUMER FINANCE TPAΠEZA A.E.', 
        '058' => 'UNION DE CREDITOS INMOBILIARIOS S.A. ESTABLECIMIENTO FINANCIERO DE CREDITO',
        '059' => 'GMAC Bank GmbH', 
        '061' => 'FCE BANK PLC', 
        '064' => 'THE ROYAL BANK OF SCOTLAND PLC',
        '069' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ ΧΑΝΙΩΝ',
        '071' => 'HSBC BANK PLC', 
        '072' => 'UNICREDIT BANK AG', 
        '073' => 'ΤΡΑΠΕΖΑ ΚΥΠΡΟΥ',
        '075' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ ΗΠΕΙΡΟΥ',
        '079' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ ΔΩΔΕΚΑΝΗΣΟΥ',
        '081' => 'BANK of AMERICA',
        '084' => 'CITIBANK INTERNATIONAL',
        '087' => 'ΠΑΓΚΡΗΤΙΑ ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ',
        '088' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ Ν. ΕΒΡΟΥ',
        '089' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ ΚΑΡ∆ΙΤΣΑΣ',
        '091' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ ΘΕΣΣΑΛΙΑΣ',
        '092' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ ΠΕΛΟΠΟΝΝΗΣΟΥ',
        '094' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ ΠΙΕΡΙΑΣ - ΟΛΥΜΠΙΑΚΗ ΠΙΣΤΗ',
        '096' => 'ΤΑΧΥΔΡΟΜΙΚΟ ΤΑΜΙΕΥΤΗΡΙΟ',
        '095' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ ∆ΡΑΜΑΣ',
        '097' => 'ΤΑΜΕΙΟ ΠΑΡΑΚΑΤΑΘΗΚΩΝ & ∆ΑΝΕΙΩΝ',
        '099' => 'ΣΥΝΕΤΑΙΡΙΣΤΙΚΗ ΤΡΑΠΕΖΑ Ν. ΣΕΡΡΩΝ',
        '102' => 'VOLKSWAGEN BANK', 
        '105' => 'BMW AUSTRIA BANK',
        '106' => 'MERCEDES-BENZ BANK POLSKA S.A.',
        '107' => 'GREEK BRANCH OF KEDR OPEN JOINSTOCK COMPANY',
        '109' => 'T.C ZIRAAT BANKASI A.S', 
        '111' => 'DEUTSCHE BANK AG', 
        '113' => 'CREDIT SUISSE (LUXEMBOURG) S.A.',
        '114' => 'FIMBANK PLC',
        );
	
    $iban = strtolower($iban);
    $Countries = array(
      'al'=>28,'ad'=>24,'at'=>20,'az'=>28,'bh'=>22,'be'=>16,'ba'=>20,'br'=>29,'bg'=>22,'cr'=>21,'hr'=>21,'cy'=>28,'cz'=>24,
      'dk'=>18,'do'=>28,'ee'=>20,'fo'=>18,'fi'=>18,'fr'=>27,'ge'=>22,'de'=>22,'gi'=>23,'gr'=>27,'gl'=>18,'gt'=>28,'hu'=>28,
      'is'=>26,'ie'=>22,'il'=>23,'it'=>27,'jo'=>30,'kz'=>20,'kw'=>30,'lv'=>21,'lb'=>28,'li'=>21,'lt'=>20,'lu'=>20,'mk'=>19,
      'mt'=>31,'mr'=>27,'mu'=>30,'mc'=>27,'md'=>24,'me'=>22,'nl'=>18,'no'=>15,'pk'=>24,'ps'=>29,'pl'=>28,'pt'=>25,'qa'=>29,
      'ro'=>24,'sm'=>27,'sa'=>24,'rs'=>22,'sk'=>24,'si'=>19,'es'=>24,'se'=>24,'ch'=>21,'tn'=>24,'tr'=>26,'ae'=>23,'gb'=>22,'vg'=>24
    );
    $Chars = array(
      'a'=>10,'b'=>11,'c'=>12,'d'=>13,'e'=>14,'f'=>15,'g'=>16,'h'=>17,'i'=>18,'j'=>19,'k'=>20,'l'=>21,'m'=>22,
      'n'=>23,'o'=>24,'p'=>25,'q'=>26,'r'=>27,'s'=>28,'t'=>29,'u'=>30,'v'=>31,'w'=>32,'x'=>33,'y'=>34,'z'=>35
    );
  
    if (strlen($iban) != $Countries[ substr($iban,0,2) ]) { return strlen($iban).' != '.$Countries[ substr($iban,0,2) ].' Λάθος'; }
  
    $MovedChar = substr($iban, 4) . substr($iban,0,4);
    $MovedCharArray = str_split($MovedChar);
    $NewString = "";
  
    foreach ($MovedCharArray as $k => $v) {
  
      if ( !is_numeric($MovedCharArray[$k]) ) {
        $MovedCharArray[$k] = $Chars[$MovedCharArray[$k]];
      }
      $NewString .= $MovedCharArray[$k];
    }

    $x = $NewString; $y = "97";
    $take = 5; $mod = "";
  
    do {
      $a = (int)$mod . substr($x, 0, $take);
      $x = substr($x, $take);
      $mod = $a % $y;
    }
    while (strlen($x));
  
    if((int)$mod == 1)
    {return $banks[substr($iban,4,3)];}
    else{return "Δεν είναι έγκυρος αριθμός IBAN";}
  }


function save_permanent_application($appdata,$afm=0)
{
    global $srv_conf;
    session_start();
    $usersdatas=$_SESSION['userdatas'];
    session_write_close();
    if($afm==0){$afm=$usersdatas['AFM'];}

    $appdata['time_last_modified']=time();

    if($appdata['FINAL']==1)
    {
        $db_application_id=add_application2db($appdata,$afm); // insert application into DB
        $appdata['db_application_id']=$db_application_id;
        // ####### move attachments ####
        $files2move=array();
        foreach($appdata as $name=>$value)
        {
            if(substr($name,0,5)=='file_')
            {
                $files2move[]='./applications/uploads/'.$db_application_id.'_'.substr($name,5);
                rename('./uploads/'.$usersdatas['AFM'].'_'.substr($name,5),'./applications/uploads/'.$db_application_id.'_'.substr($name,5));
            }
        }
    }

    if($msg=save_permanent_applications_data($appdata))
    {
        if($appdata['FINAL']==1)
        {
            $html2return= json_encode(array($msg.' Bytes Saved<br> Η αίτηση υποβλήθηκε στο Πρωτόκολλο.<br>Μπορείτε να την δείτε στις Νέες Αιτήσεις σαν υποβεβλημένη <br> και όταν πρωτοκολληθεί θα την βρείτε στο "Ιστορικό" αιτήσεων.<br>'.date('d/m/Y H:i:s',$appdata['time_last_modified']),2));
            logdata('Υποβολή αίτησης "'.$srv_conf['applications_permanent'][$appdata['application_id']].'" χρήστη '.afm_data($afm)['fullname']); 
            sendmail_protocol2application($appdata['db_application_id']);           
        }
        else
        {
            $html2return=json_encode(array($msg.' Bytes Saved<br> Η αίτηση αποθηκεύτηκε προσωρινά.<br>',1));
            logdata('Προσωρινή Αποθηκευση  αίτησης "'.$srv_conf['applications_permanent'][$appdata['application_id']].'" χρήστη '.afm_data($afm)['fullname']); 
        }
    }
    else
    {
        $html2return=json_encode(array($msg.' Παρουσιάστηκε πρόβλημα στην διαδικασία αποθήκευσης.',0));
        logdata('Παρουσιάστηκε Πρόβλημα στην Αποθηκευση  αίτησης "'.$srv_conf['applications_permanent'][$appdata['application_id']].'" χρήστη '.afm_data($afm)['fullname']); 
    }  

    return $html2return;
}



function save_permanent_applications_data($datas,$afm=0)
{
    

    session_start();
    $usersdatas=$_SESSION['userdatas'];
    session_write_close();
    if($afm!=0){$usersdatas['AFM']=$afm;}
    
    $existing_datas=array();
    $existing_datas=load_permanent_applications_data($afm);
    if(isset($existing_datas[$datas['application_id']]))
    {
        unset($existing_datas[$datas['application_id']]);
    }

    $datas2write=$existing_datas;
    
    $datas2write[$datas['application_id']]=$datas;

    $filename='./applications/permanent/'.$usersdatas['AFM'];

    $data2store= serialize($datas2write);
    $msg=file_put_contents($filename, $data2store,LOCK_EX);

    return $msg;

}



function load_permanent_applications_data($afm=0)
{
    session_start();
    $usersdatas=$_SESSION['userdatas'];
    session_write_close();
    if($afm!=0){$usersdatas['AFM']=$afm;}
    
    $filename='./applications/permanent/'.$usersdatas['AFM'];

    if(!(file_exists($filename))){$filename=__DIR__.'/applications/permanent/'.$usersdatas['AFM'];}
    $datas= file_get_contents($filename);
 
    return unserialize($datas);
}



function delete_permanent_application($id,$afm=0,$db_entry)
{
    global $srv_conf;
    
    if($afm)
    {
        $usersdatas['AFM']=$afm;
    }
    else
    {
        session_start();
        $usersdatas=$_SESSION['userdatas'];
        session_write_close();
    }
    $existing_datas=load_permanent_applications_data($usersdatas['AFM']);

    unset($existing_datas[$id]);

    $filename='./applications/permanent/'.$usersdatas['AFM'];

    $data2store= serialize($existing_datas);
    $msg=file_put_contents($filename, $data2store,LOCK_EX);

    if($db_entry) // if application is submited delete files and db entry
    {
        $files = glob('./applications/uploads/'.$db_entry.'_*');
        array_walk($files,'myunlink');
        $sql="DELETE FROM APPLICATIONS WHERE ID=$db_entry";
        dbq($sql);
        logdata('Διαγραφή Αίτησης "'.$srv_conf['applications_permanent'][$id].'" χρήστη '.afm_data($usersdatas['AFM'])['fullname'].' απο MANAGER');
    }
    else
    {
        logdata('Διαγραφή Προσωρινής Αίτησης "'.$srv_conf['applications_permanent'][$id].'" χρήστη '.afm_data($usersdatas['AFM'])['fullname']);
    }
    

    return $msg;
}

function myunlink($t)
{
    unlink($t);
}


function add_application2db($datas,$afm=0)
{
    global $con;
    session_start();
    $usersdatas=$_SESSION['userdatas'];
    session_write_close();
    if($afm==0){$afm=$usersdatas['AFM'];}

    $usersdatas['AFM']=$afm;
    $category=$datas['application_id'];
    $protocol_id=$datas['PROTOCOL_ID'];
    $protocol_date=$datas['PROTOCOL_DATE'];
    $jdatas=mysqli_real_escape_string($con,json_encode($datas));
    
    $sql="INSERT INTO APPLICATIONS(AFM,CATEGORY,PROTOCOL_ID,DATAS) VALUES('$afm','$category','0','$jdatas') ";
    $result=dbq($sql,1);
    //$row=mysqli_fetch_array($result);
    //return show_array($row);
    return $result;
}

function delete_file($filename,$afm=0)
{
        if($afm==0)
        {
            session_start();
            $usersdatas=$_SESSION['userdatas'];
            session_write_close();   
            $afm=$usersdatas['AFM'];
        }
        if(unlink('./uploads/'.$afm.'_'.$filename))
        {return 1;}else{return 0;}
}


function show_managers_permanent_application($afm,$db_application_id)
{
    global $srv_conf;

    $sql="SELECT * FROM USERS WHERE AFM=$afm";
    $result=dbq($sql);
    $usersdatas=mysqli_fetch_array($result);

    $sql="SELECT CATEGORY FROM APPLICATIONS WHERE ID='$db_application_id'";
    
    $result2=dbq($sql);
    $row2=mysqli_fetch_array($result2);
    $id=$row2['CATEGORY'];
  
    session_start();
    $_SESSION['userdatas']=$usersdatas;
    $all_session=$_SESSION;
    $usertype=$_SESSION['usertype'];
    session_write_close();
    logdata('Προβολή '.$usertype.' Αίτησης "'.$srv_conf['applications_permanent'][$id].' του Χρήστη ΑΦΜ: '.afmed($usersdatas['AFM']).' '.$usersdatas['SNAME'].' '.$usersdatas['FNAME']);

    $html2return=new_permanent_application($id,$db_application_id);
    //$html2return=show_array($usersdatas);

    return $html2return;
}


function assign_protocol2permanent_application($id,$db_application_id,$protocol_data)
{
    global $srv_conf;
    
    $html2return='Η αίτηση Πρωτοκολλήθηκε με Αριθμό <br>'.$protocol_data['PROTOCOL_ID'].'/'.$protocol_data['PROTOCOL_DATE'].'<br>ΑΦΜ: ';

    $protocol_id=$protocol_data['PROTOCOL_ID'];
    $protocol_date=$protocol_data['PROTOCOL_DATE'];
    $sql="SELECT AFM FROM APPLICATIONS WHERE ID=$db_application_id";
    $result=dbq($sql);
    $afm=mysqli_fetch_array($result)['AFM'];
    $html2return.=$afm;
    $sql="UPDATE APPLICATIONS SET PROTOCOL_ID=$protocol_id, PROTOCOL_DATE='$protocol_date' WHERE ID=$db_application_id";
    dbq($sql);
    $userdata=afm_data($afm);
    delete_permanent_application($id,$afm,0); // 0 for not deleting db entry
    tcpdfit(application2pdf_format($db_application_id),$afm.'_'.$id.'_'.$db_application_id.'.pdf','F');

    logdata('Απόδοση Πρωτοκόλλου σε Αίτηση "'.$srv_conf['applications_permanent'][$id].'" για '.$userdata['fullname']);
    return $html2return;
}

function application2pdf_format($db_application_id)
{
    global $srv_conf;
    
    $sql="SELECT * FROM APPLICATIONS WHERE ID='$db_application_id'";
    $result=dbq($sql);
    $row=mysqli_fetch_array($result);
    $id=$row['CATEGORY'];
    $appdatas=json_decode($row['DATAS'],true);

    $html2return='
    <div style="width:100%;font-size:80%;text-align:right;">
    <i>
        Αριθμός Πρωτοκόλλου: '.$row['PROTOCOL_ID'].'/'.formdate($row['PROTOCOL_DATE']).'
    </i>
    </div>

    <h2 style="text-align:center;">
        ΑΙΤΗΣΗ
    </h2>
    <table style="width:100%;margin:auto auto;">
        <tr>
            <td style="width:50%;text-align:center;">
                
            </td>
            <td style="width:50%;text-align:center;">
                ΠΡΟΣ Δ.Δ.Ε. Φλώρινας
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <hr>
            </td>
        </tr>
        <tr>
            <td style="font-size:70%;">
            '.show_usersdata_table4pdf(afm_data($row['AFM']),0) .'
            <br><br>
            <b>
                Θέμα "'.$srv_conf['applications_permanent'][$id].'"
            </b>
            </td>
            <td style="font-size:80%;text-align:center;">
            '.permenent_application_contnent($id,$db_application_id,1).'
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align:center;">
                </td>
        </tr>
    </table>
    <br>
    <div style="margin:auto auto;text-align:center;width:100%;">
        <br><br>
        <i><small>
        Η αίτηση υποβλήθηκε Ψηφιακά από το '.$srv_conf['CAS Configuration']['client_service_name'].'<br>'.date('d/m/Y - H:i:s',$appdatas['time_last_modified']).'
        </small>
        </i>    </div>';

    //$html2return=permenent_application_contnent($id,$db_application_id,1);

    return $html2return;

}

function delete_permanent_application_protocol($db_application_id)
{
    $html2return='Έγινε Διαγραφή του Πρωτοκόλλου<br>';

    $protocol_id=0;
    $protocol_date='';
    $sql="UPDATE APPLICATIONS SET PROTOCOL_ID=$protocol_id, PROTOCOL_DATE='$protocol_date' WHERE ID=$db_application_id";
    dbq($sql);

    logdata('ΔΙΑΓΡΑΦΗ Πρωτοκόλλου Αίτησης με Database ID '.$db_application_id);

    return $html2return;
}

function tcpdfit($html='',$filename='example.pdf',$fileaction='D')
{
// Include the main TCPDF library (search for installation path).
//require_once('/usr/share/php/tcpdf/tcpdf.php');
require_once('./addons/tcpdf/tcpdf.php');


// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
//$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('ΔΔΕ Φλώρινας');
//$pdf->SetTitle('TCPDF Example 001');
//$pdf->SetSubject('TCPDF Tutorial');
//$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

// set default header data
//$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE.' 001', PDF_HEADER_STRING, array(0,64,255), array(0,64,128));
$pdf->setFooterData(array(0,64,0), array(0,64,128));

// set header and footer fonts
//$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
	require_once(dirname(__FILE__).'/lang/eng.php');
	$pdf->setLanguageArray($l);
}

// ---------------------------------------------------------

// set default font subsetting mode
$pdf->setFontSubsetting(true);

// Set font
// dejavusans is a UTF-8 Unicode font, if you only need to
// print standard ASCII chars, you can use core fonts like
// helvetica or times to reduce file size.
$pdf->SetFont('dejavusans', '', 14, '', true);

// Add a page
// This method has several options, check the source code documentation for more information.
$pdf->AddPage();

// set text shadow effect
$pdf->setTextShadow(array('enabled'=>true, 'depth_w'=>0.2, 'depth_h'=>0.2, 'color'=>array(196,196,196), 'opacity'=>1, 'blend_mode'=>'Normal'));

// Print text using writeHTML()
$pdf->writeHTML($html);

// View raw source to TCPDF 
//$filename=__DIR__.'/uploads/tcpdf_pure_Html.txt';
//file_put_contents($filename, $html,LOCK_EX);

// ---------------------------------------------------------

// Close and output PDF document
// This method has several options, check the source code documentation for more information.
$pdf->Output(__DIR__ . '/applications/pdfs/'.$filename,$fileaction);

//============================================================+
// END OF FILE
//============================================================+
}

function sendmail($title,$data,$recipient_choosen='')
{
    //Create an instance; passing `true` enables exceptions
    $mail = new PHPMailer(true);
    global $srv_conf;

    try {
        //Server settings
        //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
        $mail->isSMTP();                                            //Send using SMTP
        $mail->Host       = $srv_conf['Mail Configuration']['Mail Server'];                     //Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
        $mail->Username   = $srv_conf['Mail Configuration']['Username'];                     //SMTP username
        $mail->Password   = $srv_conf['Mail Configuration']['Password'];                               //SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
        $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        //Recipients
        //$mail->setLanguage('he', './addons/phpmailer/language/directory/');
        $mail->CharSet  = 'UTF-8';
        $mail->setFrom($srv_conf['Mail Configuration']['set From mail'], $srv_conf['Mail Configuration']['set From name']);
        
        if($recipient_choosen=='')
        {
            $mail_recipient=explode(',',trim($srv_conf['Mail Configuration']['Mail Recipients']));
            foreach($mail_recipient as $recipient)
            {
                $mail->addAddress($recipient);    
            }
        }
        else
        {
            $mail->addAddress($recipient_choosen);    
        }
        //$mail->addAddress('hatziioa@sch.gr', 'Χατζηιωαννίδης Χρήστος');     //Add a recipient
        //$mail->addAddress('hatziioa@gmail.com');               //Name is optional
        $mail->addReplyTo($srv_conf['Mail Configuration']['set From mail'], $srv_conf['Mail Configuration']['set From name']);
        //$mail->addCC('cc@example.com');
        //$mail->addBCC('bcc@example.com');

        //Attachments
        //$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
        //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

        //Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = $title;
        $mail->Body    = $data;
        $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        $mail->send();
        logdata('Αποστολή mail');
    } catch (Exception $e) {
        logdata("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

function sendmail_protocoled_application($db_application_id)
{
    global $srv_conf;
    
    $sql="SELECT * FROM APPLICATIONS WHERE ID='$db_application_id'";
    $result=dbq($sql);
    $row=mysqli_fetch_array($result);
    $app_name=$srv_conf['applications_permanent'][$row['CATEGORY']];
    $afm=$row['AFM'];
    $app_data=json_decode($row['DATAS'],true);
    $app_submited_date=$app_data['time_last_modified'];

    $userdata=afm_data($afm);

    $title='ΑΙΤΗΣΗ "'.$app_name.'" - '.$userdata['fullname'];
    if($srv_conf['Mail Configuration']['phone']=='')
        {$phone='';}
    else
        {$phone='τηλ. '.$srv_conf['Mail Configuration']['phone'];}
    $data='
        <div style="width:90%;margin:auto auto;text-align:center;">
        <h3> Παρακολούθηση Αίτησης <br>"'.$app_name.'"</h3>
        <br>
        Αιτών/ούσα: '.$userdata['fullname'].'<br>
        Ημερομηνία Υποβολής: '.date("d/m/Y H:i:s",$app_submited_date).'<br>
        <a href="'.$srv_conf['Mail Configuration']['url to application'].'?db_application_id='.$db_application_id.'&AFM='.$afm.'"> Δείτε την Αίτηση </a>
        <br>
        <br>
        <br>
        '.$srv_conf['Mail Configuration']['set From name'].'<br>
        '.$srv_conf['Mail Configuration']['set From mail'].'<br>
        '.$phone.'<br>
        <br>
        ';

    sendmail($title,$data);

}

function sendmail_protocol2application($db_application_id)
{
    global $srv_conf;
    
    $sql="SELECT * FROM APPLICATIONS WHERE ID='$db_application_id'";
    $result=dbq($sql);
    $row=mysqli_fetch_array($result);
    $app_name=$srv_conf['applications_permanent'][$row['CATEGORY']];
    $afm=$row['AFM'];
    $app_data=json_decode($row['DATAS'],true);
    $app_submited_date=$app_data['time_last_modified'];

    $userdata=afm_data($afm);

    $title='Υποβολή Αίτησης "'.$app_name.'" - '.$userdata['fullname'];
    if($srv_conf['Mail Configuration']['phone']=='')
        {$phone='';}
    else
        {$phone='τηλ. '.$srv_conf['Mail Configuration']['phone'];}
    $data='
        <div style="width:90%;margin:auto auto;text-align:center;">
        <h3> Πρωτοκόλληση Αίτησης <br>"'.$app_name.'"</h3>
        <br>
        Αιτών/ούσα: '.$userdata['fullname'].'<br>
        Ημερομηνία Υποβολής: '.date("d/m/Y H:i:s",$app_submited_date).'<br>
        <a href="'.$srv_conf['Mail Configuration']['url to application'].'?db_application_id='.$db_application_id.'&AFM='.$afm.'"> Δείτε την Αίτηση </a>
        <br>
        <br>
        <br>
        '.$srv_conf['Mail Configuration']['set From name'].'<br>
        '.$srv_conf['Mail Configuration']['set From mail'].'<br>
        '.$phone.'<br>
        <br>
        ';

    sendmail($title,$data);

}


function settings_srvconf()
{
    global $srv_conf;
    
    $html2return='
    <div class="container mt-4">
        <div class="text-center mb-4">
            <h2 class="text-primary fw-bold">Προηγμένες Ρυθμίσεις Συστήματος (srv_conf)</h2>
            <p class="text-danger small">
                <i class="bi bi-exclamation-triangle-fill"></i> Προσοχή: Οι αλλαγές σε αυτές τις μεταβλητές επηρεάζουν τη συνολική λειτουργία της πλατφόρμας.
            </p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-10 text-start">
                '.settings_table($srv_conf,'srv_conf').'
            </div>
        </div>
        
        <div class="text-center mt-5 mb-5">
            <button class="btn btn-outline-secondary btn-lg shadow-sm" onclick="settings();">
                ← Επιστροφή στις Ρυθμίσεις
            </button>
        </div>
    </div>
    ';    
    
    return $html2return;
}


function settings_page()
{
    global $srv_conf;
    
    $html2return='
        <div class="container mt-4 text-center">
            <h2 class="mb-5 text-primary fw-bold">Ρυθμίσεις Εφαρμογής Aitisi Online</h2>
            
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="d-grid gap-3 mb-5">
                        <button class="btn btn-outline-primary btn-lg shadow-sm" onclick="settings_time_limits();">
                            Χρονικό Διάστημα Ενεργών Αιτήσεων
                        </button>
                        <button class="btn btn-outline-primary btn-lg shadow-sm" onclick="settings_srvconf();">
                            Ρυθμίσεις κεντρικής μεταβλητής srv_conf
                        </button>
                        <button class="btn btn-outline-primary btn-lg shadow-sm" onclick="settings_schools_table();">
                            Ρυθμίσεις Πίνακα Σχολικών Μονάδων
                        </button>
                        <button class="btn btn-outline-primary btn-lg shadow-sm" onclick="succeded_login_cards_table();">
                            Προβολή Καρτελών με Ιστορικό Επιτυχόντων Συνδέσεων
                        </button>                        
                    </div>
                    
                    <div class="card bg-light border-info mx-auto shadow-sm" style="max-width: 450px;">
                        <div class="card-header bg-info text-dark fw-bold">
                            Πληροφορίες Συστήματος
                        </div>
                        <div class="card-body text-start">
                            <ul class="list-group list-group-flush bg-transparent">
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                                    <strong>Μέγιστο μέγεθος upload:</strong> 
                                    <span class="badge bg-secondary rounded-pill">'.ini_get("upload_max_filesize").'</span>
                                </li>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                                    <strong>Διαθέσιμος χώρος:</strong> 
                                    <span class="badge bg-success rounded-pill">'.free_space().'</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <br><br>
        </div>
    ';

    return $html2return;
}

function settings_table($dt2show, $name='', $depth = 0) // AI Gemini Ideas :)
{
    $html2return = '';
    
    // Προσθήκη CSS μία φορά
    static $css_added = false;
    if (!$css_added) {
        $html2return .= '
        <style>
            .collapse-toggle .toggle-icon { transition: transform 0.3s ease; display: inline-block; }
            .collapse-toggle[aria-expanded="true"] .toggle-icon { transform: rotate(180deg); }
            .nested-depth { border-left: 2px dashed #dee2e6; margin-left: 15px; padding-left: 15px; }
            .hover-highlight:hover { background-color: #f8f9fa; }
        </style>';
        $css_added = true;
    }
    
    $bypass_settings=array('time_limits','version'); // dont show this settings
    $name_alias=array(
        'applications_permanent'=>'Ονόματα Τύπων Αιτήσεων',
        'applications_comments'=>'Οδηγίες Αιτήσεων',
        'Header_and_Footer' => 'Κεφαλίδα & Υποσέλιδο',
        //'time_limits' => 'Χρόνοι Διαθεσιμότητας',
        );

    if(is_array($dt2show))
    {
        $name_parts = explode('##', $name);
        $display_name = end($name_parts);
        if(isset($name_alias[$display_name])){$display_name=$name_alias[$display_name];}

        // ΔΙΟΡΘΩΣΗ ΕΔΩ: Χρησιμοποιούμε αυστηρή ισότητα (=== '') αντί για empty() 
        // για να μην επηρεάζεται το "0".
        if ($display_name === '') $display_name = "Ρυθμίσεις";
        
        $collapse_id = 'collapse_' . md5($name);
        
        $is_open = ($depth == 0);
        $collapse_state = $is_open ? 'collapse show' : 'collapse';
        $aria_expanded = $is_open ? 'true' : 'false';
        
        if ($depth == 0) {
            $container_class = 'card mb-4 shadow border-primary';
            $header_class = 'card-header bg-primary text-white fw-bold fs-5';
            $body_class = 'card-body bg-white';
        } elseif ($depth == 1) {
            $container_class = 'card mb-3 shadow-sm border-secondary ms-2';
            $header_class = 'card-header bg-secondary text-white fw-bold';
            $body_class = 'card-body bg-light';
        } elseif ($depth == 2) {
            $container_class = 'card mb-2 border-dark nested-depth';
            $header_class = 'card-header bg-dark text-white fw-bold small';
            $body_class = 'card-body bg-white p-2';
        } else {
            $container_class = 'border rounded mb-2 nested-depth hover-highlight';
            $header_class = 'fw-bold text-primary border-bottom pb-2 pt-2 mb-2 px-2';
            $body_class = 'px-2 pb-2';
        }

        $html2return .= '<div class="'.$container_class.'">';
        $header_style = 'cursor: pointer; user-select: none;';
        
        // Κεφαλίδα
        if ($depth < 3) {
            $html2return .= '<div class="'.$header_class.' collapse-toggle d-flex justify-content-between align-items-center" 
                                  data-bs-toggle="collapse" data-bs-target="#'.$collapse_id.'" 
                                  aria-expanded="'.$aria_expanded.'" style="'.$header_style.'">';
            $html2return .= '<span><i class="bi bi-folder2-open me-2"></i> '.$display_name.'</span>';
            $html2return .= '<span class="small fw-normal toggle-icon">▼</span>';
            $html2return .= '</div>';
        } else {
            $html2return .= '<h6 class="'.$header_class.' collapse-toggle d-flex justify-content-between align-items-center" 
                                 data-bs-toggle="collapse" data-bs-target="#'.$collapse_id.'" 
                                 aria-expanded="'.$aria_expanded.'" style="'.$header_style.'">';
            $html2return .= '<span>'.$display_name.'</span>';
            $html2return .= '<span class="text-muted small toggle-icon">▼</span>';
            $html2return .= '</h6>';
        }

        $html2return .= '<div id="'.$collapse_id.'" class="'.$collapse_state.'">';
        $html2return .= '<div class="'.$body_class.'">';

        $simple_settings_html = '';
        $nested_settings_html = '';

        foreach($dt2show as $nm => $dt)
        {
            if (in_array($nm,$bypass_settings)){continue;}
            if (is_array($dt)) {
                $nested_settings_html .= settings_table($dt, $name.'##'.$nm, $depth + 1);
            } else {
                $simple_settings_html .= '
                <div class="row mb-2 align-items-center hover-highlight py-1 rounded">
                    <label class="col-md-4 col-form-label text-md-end fw-bold text-muted small">
                        '.$nm.'
                    </label>
                    <div class="col-md-8">
                        '.settings_table($dt, $name.'##'.$nm, $depth + 1).'
                    </div>
                </div>';
            }
        }
        
        $html2return .= $simple_settings_html . $nested_settings_html;

        $html2return .= '</div></div></div>'; 
    }
    else
    {
        // Τελικό Στοιχείο
        $inputType = 'text';
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dt2show)) {
            $inputType = 'datetime-local';
        }

        $html2return .= '
        <div class="input-group input-group-sm">
            <input name="'.$name.'" type="'.$inputType.'" class="form-control" value="'.htmlspecialchars($dt2show, ENT_QUOTES, 'UTF-8').'">
            <button class="btn btn-outline-primary" type="button" onclick="change_srvconf(this);">Αλλαγή</button>
        </div>
        ';
    }

    return $html2return;
}

function settings_time_limits()
{
    global $srv_conf;
    $applications_rows='';
    
    foreach($srv_conf['applications_permanent'] as $app_id=>$app_name)
    {
        // ΕΜΦΑΝΙΣΗ ΜΟΝΟ ΤΩΝ ΑΙΤΗΣΕΩΝ ΜΕ ID >= 100
        if ((int)$app_id >= 100) {
            $default_datetime=date('Y-m-d\TH:i');
            $starts=$default_datetime;
            $ends=$default_datetime;
            $checked='';
            
            if(isset($srv_conf['time_limits'][$app_id]['checked']))
            {
                if($srv_conf['time_limits'][$app_id]['checked']=='checked')
                {
                    $starts=$srv_conf['time_limits'][$app_id]['starts'];
                    $ends=$srv_conf['time_limits'][$app_id]['ends'];
                    $checked=$srv_conf['time_limits'][$app_id]['checked'];
                }
            }

            // Δημιουργία χρωματιστού Badge ανάλογα με το αν η αίτηση είναι ενεργή
            $status_badge = (app_intime($app_id)) ? '<span class="badge bg-success">Ενεργή</span>' : '<span class="badge bg-danger">Κλειστή</span>';

            $applications_rows.='
                <tr>
                    <td class="text-start fw-bold align-middle">
                        <span class="text-muted small">#'.$app_id.'</span> - '.htmlspecialchars($app_name).'
                    </td>
                    <td class="align-middle">
                        <input type="datetime-local" class="form-control form-control-sm time_limits" id="starts_'.$app_id.'" name="starts_'.$app_id.'" value="'.$starts.'">
                    </td>
                    <td class="align-middle">
                        <input type="datetime-local" class="form-control form-control-sm time_limits" id="ends_'.$app_id.'" name="ends_'.$app_id.'" value="'.$ends.'">
                    </td>
                    <td class="align-middle text-center">
                        <div class="form-check form-switch d-flex justify-content-center fs-5">
                            <input type="checkbox" class="form-check-input time_limits" id="checked_'.$app_id.'" name="checked_'.$app_id.'" '.($checked=='checked' ? 'checked' : '').'>
                        </div>
                    </td>
                    <td class="align-middle text-center">
                        '.$status_badge.'
                    </td>
                </tr>
            ';
        }
    }

    $html2return='
    <div class="container mt-4 text-center">
        <h2 class="mb-4 text-primary fw-bold">Ρυθμίσεις Χρονικής Διάρκειας Αιτήσεων</h2>
        
        <div class="card shadow-sm mb-4">
            <div class="card-body p-0 table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-primary">
                        <tr>
                            <th class="text-start py-3 ps-3">Αίτηση</th>
                            <th class="text-center py-3" style="width: 220px;">Διαθέσιμη ΑΠΟ</th>
                            <th class="text-center py-3" style="width: 220px;">Διαθέσιμη ΩΣ</th>
                            <th class="text-center py-3" style="width: 140px;">Περιορισμός</th>
                            <th class="text-center py-3" style="width: 100px;">Κατάσταση</th>
                        </tr>
                    </thead>
                    <tbody>
                        '.$applications_rows.'
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="d-flex justify-content-center gap-3 mb-5">
            <button class="btn btn-outline-secondary btn-lg" onclick="settings();">
                ← Επιστροφή στις Ρυθμίσεις
            </button>
            <button class="btn btn-success btn-lg shadow-sm" onclick="save_time_limits();">
                Αποθήκευση Αλλαγών
            </button>
        </div>
    </div>
    ';
    
    return $html2return;
}

function settings_time_limits_depricated()
{
    global $srv_conf;
    //$srv_conf['applications_permanent']
    $applications_rows='';
    foreach($srv_conf['applications_permanent'] as $app_id=>$app_name)
    {
        $default_datetime=date('Y-m-d\TH:i');
        $starts=$default_datetime;
        $ends=$default_datetime;
        $checked='';
        if(isset($srv_conf['time_limits'][$app_id]['checked']))
            {
                if($srv_conf['time_limits'][$app_id]['checked']=='checked')
                {
                    $starts=$srv_conf['time_limits'][$app_id]['starts'];
                    $ends=$srv_conf['time_limits'][$app_id]['ends'];
                    $checked=$srv_conf['time_limits'][$app_id]['checked'];
                }
            }

        $applications_rows.='
            <tr style="border-bottom:1px dashed grey;">
                <td style="text-align:left;">
                    '.$app_name.'
                </td>
                <td style="text-align:center;">
                    <input type="datetime-local" class="time_limits" id="starts_'.$app_id.'" name="starts_'.$app_id.'" value="'.$starts.'">
                </td>
                <td style="text-align:center;">
                    <input type="datetime-local" class="time_limits" id="ends_'.$app_id.'" name="ends_'.$app_id.'" value="'.$ends.'">
                </td>
                <td style="text-align:center;">
                    <input type="checkbox" class="time_limits" id="checked_'.$app_id.'" name="checked_'.$app_id.'" '.$checked.'>
                    '.((app_intime($app_id))?'v':'x').'
                </td>
            </tr>
        ';
    }

    $html2return='
    <div style="width:95%;margin: auto auto;text-align:center">
    <br>
    <h2> Ρυθμίσεις Χρονικής Διάρκειας Διαθεσιμότητας Αιτήσεων</h>
    <br><br>   
    <table style="width:80%;margin:auto auto;text-align:center;">
        <thead>
            <tr>
                <th style="text-align:center;font-weight:bold;">
                    Αίτηση
                </th>
                <th style="text-align:center;font-weight:bold;">
                    Διαθέσιμη ΑΠΟ
                </th>
                <th style="text-align:center;font-weight:bold;">
                    Διαθέσιμη ΩΣ
                </th>
                <th style="text-align:center;font-weight:bold;">
                    Ενεργοποίηση<br>Περιορισμού
                </th>
            </tr>
        </thead>
        <tbody>
            '.$applications_rows.'
        </tbody>
    </table>
    <br>
    <table style="width:90%;margin:auto auto;text-align:center;">
        <tr>
            <td style="width:50%;">
                <button class="button02" onclick="settings();"> Επιστροφή στις Ρυθμίσεις</button>
            </td>
            <td>
                <button class="button02" onclick="save_time_limits();"> Αποθήκευση </button>
            </td>
        </tr>
    </table>
            
    <br><br><br>
    </div>
    ';
    return $html2return;
}

function free_space($simple=0)
{
    $bytes = disk_free_space(".");
    return bytes2readable($bytes, $simple);

}

function bytes2readable($bytes,$simple=0)
{
    $si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
    $base = 1024;
    $class = min((int)log($bytes , $base) , count($si_prefix) - 1);
    //echo $bytes . '<br />';
    if ($simple){return $bytes;}
    else{
        return sprintf('%1.2f' , $bytes / pow($base,$class)) . ' ' . $si_prefix[$class] ;
    }    
}

function change_srvconf($n,$d)
{
    global $srv_conf;
    $key=explode('##',$n);

    switch (count($key)-1)
    {
        case 1:
            $srv_conf[$key[1]]=$d;
            break;
        case 2:
            $srv_conf[$key[1]][$key[2]]=$d;
            break;
        case 3:
            $srv_conf[$key[1]][$key[2]][$key[3]]=$d;
            break;
        case 4:
            $srv_conf[$key[1]][$key[2]][$key[3]][$key[4]]=$d;
            break;
        case 5:
            $srv_conf[$key[1]][$key[2]][$key[3]][$key[4]][$key[5]]=$d;
            break;            
        default:
            break;
    }
    save_configuration();
    return '$srv_conf change on depth '.count($key).' for new data '.$d;
}
 
function save_time_limits($datas)
{
    global $srv_conf;
    $time_limits_data=array();

    foreach($datas as $name=>$time_limit_data)
    {
        $name_data=explode('_',$name);
        $time_limits_data[$name_data[1]][$name_data[0]]=$time_limit_data;
    }
    $srv_conf['time_limits']=$time_limits_data;
    save_configuration();
    //$html2return=show_array($srv_conf['time_limits']);
    return 'Οι ρυθμίσεις αποθηκεύτηκαν.';
    //return show_array($srv_conf['time_limits'][100]);
    //return app_intime(100);

}


function date2time_timestamp($d) // converts datetime to timestamp
{
    $s=explode('T',$d);
    $s_date=explode('-',$s[0]);
    if(count($s_date)==3)
    {
        $date_time_2_timestamp=$s_date[2].'-'.$s_date[1].'-'.$s_date[0].' '.$s[1];
    }
    else
    {
        $s_date=explode('/',$s[0]);
        $date_time_2_timestamp=$s_date[1].'-'.$s_date[0].'-'.$s_date[0].' '.$s[1];
    }
    return strtotime($date_time_2_timestamp);
}


function app_intime($app_id,$time2check=0) //checks if application is available in time limits that are set
{
    global $srv_conf;
    if($time2check==0){$time2check=time();}
    if($srv_conf['time_limits'][$app_id]['checked']=='checked')
    {
        if(
            (date2time_timestamp($srv_conf['time_limits'][$app_id]['starts']) < $time2check)
            && 
            ($time2check<date2time_timestamp($srv_conf['time_limits'][$app_id]['ends']))
            )
        {$html2return=true;}
        else
        {$html2return=false;}
    }
    else
    {
        $html2return=true;
    }

    return $html2return;
}

function app_infuture($app_id)
{
    global $srv_conf;
    if($srv_conf['time_limits'][$app_id]['checked']=='checked')
    {
        if(time()<date2time_timestamp($srv_conf['time_limits'][$app_id]['ends']))
        {$html2return=true;}
        else
        {$html2return=false;}
    }
    else
    {
        $html2return=true;
    }

    return $html2return;    
}

function show_managers_excel_downloads()
{
    $html2return='
    <div style="text-align:center;margin: auto auto;width:95%;">
        <h2> Λήψεις στοιχείων αιτήσεων</h2>
        <br>
        <div style="text-align:center;margin: auto auto;width:80%;">
        <table style="width:100%;margin: auto auto;border: 1px solid green;">
            <tr>
                <td>
            <button class="button02" style="width:100%;text-align:left;padding-left:10px;" onclick="excel_app100();">Excel Δηλώσεων Υπεραριθμίας</button><br><br>
                </td>
            </tr>
            <tr>
                <td>
            <button class="button02" style="width:100%;text-align:left;padding-left:10px;" onclick="excel_app101();">Excel Αιτήσεων Βελτίωσης και Οριστικής Τοποθέτησης Γενικής Παιδείας</button><br><br>
                </td>
            </tr>
            <tr>
                <td>
            <button class="button02" style="width:100%;text-align:left;padding-left:10px;" onclick="excel_app102();">Excel Αιτήσεων Βελτίωσης και Οριστικής Τοποθέτησης EAE</button><br><br>
                </td>
            </tr>
            <tr>
                <td>
            <button class="button02" style="width:100%;text-align:left;padding-left:10px;" onclick="excel_app103();">Excel Αιτήσεων για Συμπλήρωση Ωραρίου Εκπαιδευτικών με Οργανική θέση στην ΔΔΕ Φλώρινας</button><br><br>
                </td>  
            </tr>
            <tr>
                <td>
            <button class="button02" style="width:100%;text-align:left;padding-left:10px;" onclick="excel_app104();">Excel Αιτήσεων για Τοποθέτηση & Συμπλήρωση Ωραρίου Εκπαιδευτικών στην Διάθεση του ΠΥΣΔΕ Φλώρινας ή ΑΠΟΣΠΑΣΜΕΝΩΝ από άλλα ΠΥΣΔΕ</button><br><br>
                </td>
            </tr>
        </table>
        </div>
    </div>
    ';

    return $html2return;
}

function application_available_afm_check($app_id)
{
    //global $user_application_filter;
    $user_application_filter[100]['enable']=0; // ΑΦΜ με δικαίωμα Δήλωσης Υπεραριθιμίες
    $user_application_filter[100]['afm']=array(); 
    $user_application_filter[101]['enable']=0; // ΑΦΜ με δικαίωμα Αίτησης Βελτίωσης και Οριστικής Τοποθέτησης Γενικής Παιδείας
    $user_application_filter[101]['afm']=array();
    $user_application_filter[102]['enable']=0; // ΑΦΜ με δικαίωμα Αίτησης Βελτίωσης και Οριστικής Τοποθέτησης Ειδικής Αγωγής
    $user_application_filter[102]['afm']=array(

        '140074312', //ΜΑΓΟΥΛΟΠΟΥΛΟΥ
        '120116261', //ΑΠΤΑΛΙΔΗΣ
        '133123081', //ΒΟΓΛΙΔΗΣ
        '118868967', //ΓΟΥΝΑΡΗΣ
        '126751556', //ΓΑΒΑΝΟΥ
        '133119713', //ΧΑΤΖΗΤΡΥΦΩΝΟΣ
        '142287859', //ΤΣΙΡΟΠΟΥΛΟΥ
        '135028466', //ΤΟΥΡΤΟΥΡΗΣ
        //'049498005'
    );
    $user_application_filter[103]['enable']=0; // ΑΦΜ με δικαίωμα Δήλωσης Συμπλήρωση Ωραρίου με οργανική στην ΔΔΕ Φλώρινας
    $user_application_filter[103]['afm']=array(); 
    $user_application_filter[104]['enable']=1; // ΑΦΜ με δικαίωμα Δήλωσης Συμπλήρωση Ωραρίου με Διάθεση ή Αποσπασμένων από άλλα ΠΥΣΔΕ
    $user_application_filter[104]['afm']=array(
    // διαθεση 2024
    
    // Αποσπασμένοι 2024
    //'049498005', //ΧΑΤΖΗΙΩΑΝΝΙΔΗΣ ΧΡΗΣΤΟΣ
    
    // Αποσπασμένοι 2025
    //'108500170', // ΠΑΠΑΣ ΕΥΣΤΡΑΤΙΟΣ
    


    ); 
 
    $html2return=1;
    
    if(isset($user_application_filter[$app_id]))
    {
        if($user_application_filter[$app_id]['enable']==1)
        {
            session_start();
            $afm=$_SESSION['userdatas']['AFM'];
            $usertype=$_SESSION['usertype'];
            session_write_close();
            if(!(in_array($afm,$user_application_filter[$app_id]['afm'])))
            {$html2return=0;}
            if(($usertype=='manager')||($usertype=='secretary')){$html2return=1;}

        }
    }

    return $html2return;
}

function application_available_am_check($app_id)
{
    //global $user_application_filter;
    $user_application_filter[100]['enable']=0; // ΑΦΜ με δικαίωμα Δήλωσης Υπεραριθιμίες
    $user_application_filter[100]['am']=array(); 
    $user_application_filter[101]['enable']=1; // ΑΦΜ με δικαίωμα Αίτησης Βελτίωσης και Οριστικής Τοποθέτησης Γενικής Παιδείας
    $user_application_filter[101]['am']=array();
    $user_application_filter[102]['enable']=1; // ΑΦΜ με δικαίωμα Αίτησης Βελτίωσης και Οριστικής Τοποθέτησης Ειδικής Αγωγής
    $user_application_filter[102]['am']=array();
    $user_application_filter[103]['enable']=0; // ΑΦΜ με δικαίωμα Δήλωσης Συμπλήρωση Ωραρίου με οργανική στην ΔΔΕ Φλώρινας
    $user_application_filter[103]['am']=array(); 
    $user_application_filter[104]['enable']=1; // ΑΦΜ με δικαίωμα Δήλωσης Συμπλήρωση Ωραρίου με Διάθεση ή Αποσπασμένων από άλλα ΠΥΣΔΕ
    $user_application_filter[104]['am']=array(
        // Διάθεση 2025
        '739647', //ΑΘΑΝΑΣΙΟΥ ΜΑΡΙΑ
        '712072', //ΑΔΑΜΑΝΤΙΟΥ ΠΟΛΥΞΕΝΗ
        '739721', // ΚΥΡΚΟΣ ΓΕΩΡΓΙΟΣ
        '739986', //ΒΡΕΚΑ ΜΑΛΑΜΑΤΗ
        '723867', // ΚΑΠΑΝΤΖΑΚΗ ΜΑΡΙΑ
        '730898', // ΒΡΟΝΤΖΟΣ ΙΩΑΝΝΗΣ
        '740306', // ΙΩΑΝΝΙΔΟΥ ΙΩΑΝΝΑ
        '729264', // ΣΙΠΑΚΗ ΕΙΡΗΝΗ
        '740322', // ΖΕΜΠΕΚΑΚΗ ΜΑΡΙΑ
        '219519', // ΠΑΠΑΔΟΠΟΥΛΟΥ ΑΛΕΞΑΝΔΡΑ
        '219658', // ΠΑΠΑΔΟΠΟΥΛΟΥ ΟΛΓΑ
        '702326', // ΚΟΥΛΗΣ ΛΑΕΡΤΗΣ
        '732919', // ΤΣΙΩΝΗΣ ΝΕΚΤΑΡΙΟΣ
        '719402', // ΓΟΥΔΟΓΙΑΝΝΗ ΖΩΗ

        // Απόσπαση 2025
        '731220', //ΑΛΕΞΑΝΔΡΙΔΗΣ ΣΩΤΗΡΙΟΣ
        //'723726', //ΒΛΑΧΟΔΗΜΟΣ  ΚΩΝΣΤΑΝΤΙΝΟΣ
        '710342', //ΓΙΑΓΚΟΥΛΗΣ ΤΡΥΦΩΝ
        '208999', //ΓΙΩΑΝΝΗΣ ΠΑΝΑΓΙΩΤΗΣ
        '215897', //ΔΗΜΗΤΡΑΚΗΣ ΑΘΑΝΑΣΙΟΣ
        '178395', //ΔΗΜΗΤΡΙΟΥ ΖΗΣΗΣ
        '710209', //ΔΗΝΑΚΗ ΣΟΦΙΑ
        //'183320', //ΔΙΑΚΟΥΜΗΣ ΕΠΑΜΕΙΝΩΝΔΑΣ
        '730346', //ΙΩΑΝΝΙΔΟΥ ΟΛΓΑ
        '742047', //ΚΟΪΜΤΣΙΔΗΣ ΘΕΟΔΟΣΙΟΣ
        '741651', //ΚΡΟΥΣΟΡΑΤΗΣ ΔΗΜΗΤΡΙΟΣ
        '710271', //ΛΑΖΑΡΙΔΟΥ ΧΡΙΣΤΙΝΑ
        //'730291', //ΜΑΝΑΝΗ ΔΗΜΗΤΡΑ
        '729149', //ΜΠΛΕΤΣΟΥ ΕΛΕΥΘΕΡΙΑ
        '741819', //ΜΩΥΣΙΑΔΟΥ ΑΝΑΣΤΑΣΙΑ
        '701588', //ΝΑΚΟΥ ΕΛΕΝΗ
        '704742', //ΝΑΛΜΠΑΝΤΙΔΟΥ ΒΑΣΙΛΙΚΗ
        '709790', //ΠΑΝΑΓΙΩΤΙΔΟΥ ΑΘΑΝΑΣΙΑ
        '223488', // ΠΑΠΑΣ ΕΥΣΤΡΑΤΙΟΣ
        '723024', //ΠΑΣΧΑΛΙΔΟΥ ΧΑΡΙΚΛΕΙΑ
        '172036', //ΠΟΥΛΑΡΑΚΗ ΑΙΚΑΤΕΡΙΝΗ
        '215695', //ΡΑΛΛΗ ΜΑΡΙΝΑ
        '710217', //ΣΑΡΟΓΛΟΥ ΒΕΡΟΝΙΚΗ
        '730618', //ΤΖΩΒΑΪΡΗΣ ΣΩΤΗΡΙΟΣ
        '733018', // ΤΣΑΝΤΕΦΣΚΗ ΕΛΕΝΗ
        '709719', //ΧΑΡΑΒΙΤΣΙΔΗΣ ΜΙΛΤΙΑΔΗΣ
        '215827', //ΧΑΤΖΗΙΩΑΝΝΟΥ ΙΩΑΝΝΗΣ
        '704833', //ΧΡΙΣΤΙΔΗΣ ΠΕΤΡΟΣ
        '211118', // HATZIIOA
        '0200843', //KOLOKONTES
        '167181', // ΣΤΥΛΙΑΔΗΣ ΚΩΝΣΤΑΝΙΝΟΣ
        '170637', // ΤΟΝΑΣ ΖΑΧΑΡΙΑΣ
        '205592', // ΤΖΙΩΓΚΑ ΑΝΑΣΤΑΣΙΑ

    ); 
 
    $html2return=1;
    
    if(isset($user_application_filter[$app_id]))
    {
        if($user_application_filter[$app_id]['enable']==1)
        {
            session_start();
            $am=$_SESSION['userdatas']['AM'];
            $usertype=$_SESSION['usertype'];
            session_write_close();
            if(!(in_array($am,$user_application_filter[$app_id]['am'])))
            {$html2return=0;}
            if(($usertype=='manager')||($usertype=='secretary')){$html2return=1;}

        }
    }
    return $html2return;
}


function check_diathesi($afm,$organiki)
{
   // διαθεση 2023
    $diathesi=array(
        '120116261', //ΑΠΤΑΛΙΔΗΣ
        '118868967', //ΓΟΥΝΑΡΗΣ
        '126751556', //ΓΑΒΑΝΟΥ
        '133119713', //ΧΑΤΖΗΤΡΥΦΩΝΟΣ
        '142287859', //ΤΣΙΡΟΠΟΥΛΟΥ
        '135028466', //ΤΟΥΡΤΟΥΡΗΣ

        '073762379', //ΓΚΕΚΑΣ
        '111013110', //ΚΑΡΑΔΟΣΙΔΗΣ
        '128418964', //ΚΟΠΑΡΑΝΙΔΗΣ
        '126842509', //ΤΣΟΥΚΑΛΑ
        '137720247', //ΣΤΕΦΑΝΟΒΙΤΣ
        '108297140', //ΔΙΔΑΣΚΑΛΟΥ
        '118500767', //ΚΕΣΙΔΟΥ
        '118546192', //ΜΑΝΤΡΑΤΖΗ
        '118540544', //ΣΤΑΘΟΠΟΥΛΟΥ
        '070322960', //ΑΤΣΙΟΣ
        '064123234', //ΔΗΜΤΣΑΣ
        '149213947', //ΚΑΡΑΦΥΛΛΙΔΟΥ
        '054846557', //ΤΖΕΤΖΗ
        '108206640', //ΑΔΑΜΑΝΤΙΟΥ
        '131524446', //ΚΑΠΑΝΤΖΑΚΗ
        '045453426', //ΔΟΥΚΑ
        '108239159', //ΠΑΠΑΣΩΤΗΡΙΟΥ
        '045599060', //ΚΟΥΛΗΣ
        //'049498005'
    );
    $diathesi=array(
    // διαθεση 2024
        '118512059',// Παπαδοπούλου Αλέξάνδρα
        '043334640', // Παπαδοπούλου Όλγα
        '045599060', // Κούλης Λάέρτης
        '108206640', // Αδαμαντίου Πολυξένη
        '071158562', // Μήτσου Χρυσή
    );



    $html2return=$organiki;
    if(in_array($afm,$diathesi))
    {$html2return='Διάθεση ΠΥΣΔΕ';}
    return $html2return;
}

function settings_schools_table()
{
    global $srv_conf;
    $schools_file = __DIR__ . '/data/schools_' . $srv_conf['Main Configuration']['dideid'] . '.json';
    $applications_names = $srv_conf['applications_permanent']; 

    // 1. ΔΗΜΙΟΥΡΓΙΑ ΑΡΧΕΙΟΥ ΑΝ ΔΕΝ ΥΠΑΡΧΕΙ
    if (!file_exists($schools_file)) {
        // [Ο κώδικας δημιουργίας παραμένει ως έχει αν χρειαστεί]
    }

    // 2. ΑΝΑΓΝΩΣΗ ΤΟΥ ΑΡΧΕΙΟΥ JSON
    $json_data = file_get_contents($schools_file);
    $data = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return '<div class="alert alert-danger">Σφάλμα κατά την ανάγνωση του αρχείου σχολείων.</div>';
    }

    // 3. ΚΑΤΑΣΚΕΥΗ HTML ΠΙΝΑΚΑ ΚΑΙ ΔΙΑΣΥΝΔΕΣΗΣ
    $html = '<div class="card p-3 shadow-sm my-3">';
    
    // ΚΟΥΜΠΙ ΕΠΙΣΤΡΟΦΗΣ & ΠΡΟΣΘΗΚΗΣ
    $html .= '<div class="mb-3 d-flex justify-content-between align-items-center">';
    $html .= '<button class="btn btn-outline-secondary" onclick="settings();">← Επιστροφή στις Ρυθμίσεις</button>';
    $html .= '<button class="btn btn-success" onclick="openSchoolModal(\'add\')">Προσθήκη Νέου Σχολείου</button>';
    $html .= '</div>';

    // DROPDOWN ΕΠΙΛΟΓΗΣ ΑΙΤΗΣΗΣ ΓΙΑ ΜΑΖΙΚΗ ΔΙΑΧΕΙΡΙΣΗ
    $html .= '<div class="card bg-light p-3 mb-4 border-primary">';
    $html .= '<h5><label for="app_select" class="form-label text-primary fw-bold">Διαχείριση Διαθεσιμότητας Σχολείων ανά Αίτηση</label></h5>';
    $html .= '<select id="app_select" class="form-select" onchange="loadApplicationExclusions(this.value)">';
    $html .= '<option value="">-- Επιλέξτε μια αίτηση για να εμφανιστούν τα διαθέσιμα και μη διαθέσιμα σχολεία --</option>';
    
    foreach ($applications_names as $id => $name) {
        // ΕΜΦΑΝΙΣΗ ΜΟΝΟ ΤΩΝ ΑΙΤΗΣΕΩΝ ΜΕ ID >= 100 ΣΤΟ DROPDOWN
        if ((int)$id >= 100) {
            $html .= '<option value="'.$id.'">#'.$id.' - '.htmlspecialchars($name).'</option>';
        }
    }
    $html .= '</select>';
    $html .= '<div id="application_exclusion_zone" class="mt-3"></div>';
    $html .= '</div>';
    
    // Κεντρικός Πίνακας Σχολείων
    $html .= '<h5 class="text-secondary mt-4 mb-2">Γενικός Κατάλογος & Κατάσταση Εξαιρέσεων</h5>';
    $html .= '<table id="schools_datatable" class="table table-striped table-bordered table-hover" style="width:100%">';
    $html .= '<thead>
                <tr>
                    <th>Κωδικός Σχολείου</th>
                    <th>Ονομασία Σχολικής Μονάδας</th>
                    <th>Εξαιρείται από τις Αιτήσεις</th>
                    <th>Ενέργειες</th>
                </tr>
              </thead>';
    $html .= '<tbody>';

    foreach ($data['schools'] as $code => $name) {
        $exclusions_html = array();
        $raw_exclusions = array();
        
        foreach ($data['exclude_schools'] as $app_id => $excluded_codes) {
            // ΕΜΦΑΝΙΣΗ BADGES ΜΟΝΟ ΓΙΑ ID >= 100
            if ((int)$app_id >= 100 && in_array($code, $excluded_codes)) {
                $app_name = isset($applications_names[$app_id]) ? htmlspecialchars($applications_names[$app_id]) : 'Άγνωστη Αίτηση';
                $exclusions_html[] = '<span class="badge bg-danger" title="' . $app_name . '" style="cursor:help;">#' . $app_id . '</span>';
                $raw_exclusions[] = $app_id;
            }
        }
        $exclusions_output = empty($exclusions_html) ? '<span class="badge bg-success">Καμία εξαίρεση</span>' : implode(' ', $exclusions_html);
        $raw_exc_str = implode(',', $raw_exclusions);

        $html .= '<tr>';
        $html .= '<td><strong>' . htmlspecialchars($code) . '</strong></td>';
        $html .= '<td>' . htmlspecialchars($name) . '</td>';
        $html .= '<td>' . $exclusions_output . '</td>';
        $html .= '<td>
                    <button class="btn btn-sm btn-warning" onclick="openSchoolModal(\'edit\', \''.$code.'\', \''.htmlspecialchars($name, ENT_QUOTES).'\', \''.$raw_exc_str.'\')">Επεξ.</button> 
                    <button class="btn btn-sm btn-danger" onclick="deleteSchool(\''.$code.'\')">Διαγραφή</button>
                  </td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    // HTML ΓΙΑ ΤΟ BOOTSTRAP MODAL (Η Φόρμα)
    $html .= '
    <div class="modal fade" id="schoolModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="schoolModalTitle">Σχολική Μονάδα</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="sch_action" value="add">
            <input type="hidden" id="sch_old_code" value="">
            <div class="mb-3">
              <label class="form-label">Κωδικός Σχολείου</label>
              <input type="text" class="form-control" id="sch_code" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Ονομασία Σχολείου</label>
              <input type="text" class="form-control" id="sch_name" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Εξαίρεση από Αιτήσεις:</label><br>';
              
              if(!empty($applications_names)){
                  foreach ($applications_names as $app_id => $app_name) {
                      // ΕΜΦΑΝΙΣΗ CHECKBOXES ΜΟΝΟ ΓΙΑ ID >= 100
                      if ((int)$app_id >= 100) {
                          $html .= '<div class="form-check">
                                      <input class="form-check-input exclusion-checkbox" type="checkbox" value="'.$app_id.'" id="chk_'.$app_id.'">
                                      <label class="form-check-label" for="chk_'.$app_id.'">#'.$app_id.' - '.htmlspecialchars($app_name).'</label>
                                    </div>';
                      }
                  }
              }
              
    $html .= '</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
            <button type="button" class="btn btn-primary" onclick="saveSchool()">Αποθήκευση</button>
          </div>
        </div>
      </div>
    </div>';

    return $html;
}

// ΝΕΑ ΣΥΝΑΡΤΗΣΗ: Επιστρέφει το Side-by-Side Interface για την επιλεγμένη αίτηση
function get_application_exclusions_view($app_id) {
    global $srv_conf;
    $schools_file = __DIR__ . '/data/schools_' . $srv_conf['Main Configuration']['dideid'] . '.json';
    
    if (!file_exists($schools_file)) return 'File not found';
    
    $data = json_decode(file_get_contents($schools_file), true);
    $excluded_codes = isset($data['exclude_schools'][$app_id]) ? $data['exclude_schools'][$app_id] : array();
    
    $html = '<div class="row mt-3">';
    
    // ΑΡΙΣΤΕΡΗ ΣΤΗΛΗ: ΔΙΑΘΕΣΙΜΑ ΣΧΟΛΕΙΑ
    $html .= '<div class="col-md-6">';
    $html .= '<div class="card border-success">';
    $html .= '<div class="card-header bg-success text-white fw-bold">🟢 Διαθέσιμα Σχολεία στην Αίτηση (Ενεργά)</div>';
    $html .= '<div class="card-body p-2" style="max-height: 350px; overflow-y: auto;">';
    $html .= '<ul class="list-group list-group-flush">';
    
    // ΔΕΞΙΑ ΣΤΗΛΗ: ΜΗ ΔΙΑΘΕΣΙΜΑ (ΕΞΑΙΡΟΥΜΕΝΑ)
    $html_ex = '<div class="col-md-6">';
    $html_ex .= '<div class="card border-danger">';
    $html_ex .= '<div class="card-header bg-danger text-white fw-bold">🔴 Μη Διαθέσιμα Σχολεία (Εξαιρούμενα)</div>';
    $html_ex .= '<div class="card-body p-2" style="max-height: 350px; overflow-y: auto;">';
    $html_ex .= '<ul class="list-group list-group-flush">';
    
    $has_avail = false;
    $has_ex = false;
    
    // Ταξινόμηση σχολείων αλφαβητικά βάσει ονόματος για ευκολία
    asort($data['schools']);
    
    foreach ($data['schools'] as $code => $name) {
        if (in_array($code, $excluded_codes)) {
            // Αν είναι εξαιρούμενο, μπαίνει στη δεξιά στήλη και έχει κουμπί "Προσθήκη / Ενεργοποίηση"
            $html_ex .= '<li class="list-group-item d-flex justify-content-between align-items-center p-2">';
            $html_ex .= '<span><small class="text-muted">['.$code.']</small> ' . htmlspecialchars($name) . '</span>';
            $html_ex .= '<button class="btn btn-sm btn-success py-0" onclick="toggleExclusion(\''.$app_id.'\', \''.$code.'\', \'remove\')">◀ Ενεργοποίηση</button>';
            $html_ex .= '</li>';
            $has_ex = true;
        } else {
            // Αν είναι ενεργό, μπαίνει στην αριστερή στήλη και έχει κουμπί "Αφαίρεση / Εξαίρεση"
            $html .= '<li class="list-group-item d-flex justify-content-between align-items-center p-2">';
            $html .= '<span><small class="text-muted">['.$code.']</small> ' . htmlspecialchars($name) . '</span>';
            $html .= '<button class="btn btn-sm btn-danger py-0" onclick="toggleExclusion(\''.$app_id.'\', \''.$code.'\', \'add\')">Εξαίρεση ▶</button>';
            $html .= '</li>';
            $has_avail = true;
        }
    }
    
    if(!$has_avail) $html .= '<li class="list-group-item text-center text-muted">Όλα τα σχολεία είναι εξαιρούμενα.</li>';
    if(!$has_ex) $html_ex .= '<li class="list-group-item text-center text-muted">Κανένα σχολείο δεν εξαιρείται.</li>';
    
    $html .= '</ul></div></div></div>';
    $html_ex .= '</ul></div></div></div>';
    
    $html .= $html_ex . '</div>';
    return $html;
}

// ΝΕΑ ΣΥΝΑΡΤΗΣΗ: Προσθαφαιρεί ένα σχολείο από τη λίστα εξαίρεσης μιας συγκεκριμένης αίτησης
function toggle_school_exclusion($app_id, $code, $action) {
    global $srv_conf;
    $schools_file = __DIR__ . '/data/schools_' . $srv_conf['Main Configuration']['dideid'] . '.json';
    if (!file_exists($schools_file)) return 'File not found';
    
    $data = json_decode(file_get_contents($schools_file), true);
    if(!isset($data['exclude_schools'][$app_id])) {
        $data['exclude_schools'][$app_id] = array();
    }
    
    $key = array_search($code, $data['exclude_schools'][$app_id]);
    
    if ($action === 'add' && $key === false) {
        $data['exclude_schools'][$app_id][] = $code;
    } elseif ($action === 'remove' && $key !== false) {
        unset($data['exclude_schools'][$app_id][$key]);
    }
    
    $data['exclude_schools'][$app_id] = array_values($data['exclude_schools'][$app_id]);
    
    if (file_put_contents($schools_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
        return 'OK';
    }
    return 'Error writing file';
}



// --- Αποθήκευση / Ενημέρωση Σχολείου ---
function save_school_entry($action, $old_code, $code, $name, $exclusions_json) {
    global $srv_conf;
    $schools_file = __DIR__ . '/data/schools_' . $srv_conf['Main Configuration']['dideid'] . '.json';
    
    if (!file_exists($schools_file)) return 'File not found';
    
    $data = json_decode(file_get_contents($schools_file), true);
    $exclusions = json_decode($exclusions_json, true);

    // Αν είναι Επεξεργασία και άλλαξε ο κωδικός (ID), πρέπει να διαγράψουμε τον παλιό
    if ($action === 'edit' && $old_code !== $code) {
        unset($data['schools'][$old_code]);
        // Αφαίρεση του παλιού κωδικού από όλες τις λίστες αποκλεισμού
        foreach ($data['exclude_schools'] as $app_id => $codes) {
            if (($key = array_search($old_code, $codes)) !== false) {
                unset($data['exclude_schools'][$app_id][$key]);
            }
        }
    }

    // Προσθήκη / Ενημέρωση του Σχολείου
    $data['schools'][$code] = $name;

    // Ενημέρωση των λιστών αποκλεισμού
    foreach ($srv_conf['applications_permanent'] as $app_id => $app_name) {
        if (!isset($data['exclude_schools'][$app_id])) {
            $data['exclude_schools'][$app_id] = array();
        }
        
        $is_checked = in_array($app_id, $exclusions);
        $key = array_search($code, $data['exclude_schools'][$app_id]);
        
        if ($is_checked && $key === false) {
            // Αν επιλέχθηκε και δεν υπάρχει, το προσθέτουμε
            $data['exclude_schools'][$app_id][] = $code;
            // Αναδιάταξη index (προαιρετικό, αλλά κρατάει το JSON καθαρό)
            $data['exclude_schools'][$app_id] = array_values($data['exclude_schools'][$app_id]); 
        } elseif (!$is_checked && $key !== false) {
            // Αν ΔΕΝ επιλέχθηκε και υπάρχει, το διαγράφουμε
            unset($data['exclude_schools'][$app_id][$key]);
            $data['exclude_schools'][$app_id] = array_values($data['exclude_schools'][$app_id]);
        }
    }

    // Αποθήκευση
    if (file_put_contents($schools_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
        return 'OK';
    } else {
        return 'Cannot write file';
    }
}

// --- Διαγραφή Σχολείου ---
function delete_school_entry($code) {
    global $srv_conf;
    $schools_file = __DIR__ . '/data/schools_' . $srv_conf['Main Configuration']['dideid'] . '.json';
    
    if (!file_exists($schools_file)) return 'File not found';
    
    $data = json_decode(file_get_contents($schools_file), true);

    // Διαγραφή από τον πίνακα των σχολείων
    if (isset($data['schools'][$code])) {
        unset($data['schools'][$code]);
    }

    // Διαγραφή από όλες τις λίστες αποκλεισμού (exclude_schools)
    foreach ($data['exclude_schools'] as $app_id => $codes) {
        if (($key = array_search($code, $codes)) !== false) {
            unset($data['exclude_schools'][$app_id][$key]);
            // Επαναφορά των indexes του array
            $data['exclude_schools'][$app_id] = array_values($data['exclude_schools'][$app_id]);
        }
    }

    // Αποθήκευση
    if (file_put_contents($schools_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
        return 'OK';
    } else {
        return 'Cannot write file';
    }
}

function succeded_login_cards_table()
{
    $dir = __DIR__ . '/sch_feedback/';
    $options = '<option value="">-- Επιλέξτε μήνα καταγραφής --</option>';
    
    // Αναζήτηση όλων των αρχείων xml στον φάκελο sch_feedback
    if (is_dir($dir)) {
        $files = glob($dir . 'cas_credentials_*.xml');
        if ($files) {
            rsort($files); // Ταξινόμηση φθίνουσα (ο πιο πρόσφατος μήνας πρώτος)
            foreach ($files as $file) {
                $basename = basename($file);
                // Εξαγωγή του YYYYMM για πιο όμορφη εμφάνιση
                $display_name = str_replace(array('cas_credentials_', '.xml'), '', $basename);
                if (strlen($display_name) == 6) {
                    $year = substr($display_name, 0, 4);
                    $month = substr($display_name, 4, 2);
                    $display_name = $month . ' / ' . $year;
                }
                
                $options .= '<option value="' . htmlspecialchars($basename) . '">📄 ' . $display_name . ' (' . htmlspecialchars($basename) . ')</option>';
            }
        } else {
            $options = '<option value="">Δεν βρέθηκαν αρχεία καταγραφής</option>';
        }
    }

    $html = '
    <div class="container-fluid mt-4 mb-5">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                <h4 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2"></i>Ιστορικό Επιτυχόντων Συνδέσεων CAS</h4>
                <button class="btn btn-sm btn-outline-light rounded-pill shadow-sm px-3" onclick="settings();">
                    <i class="bi bi-arrow-left"></i> Επιστροφή
                </button>
            </div>
            <div class="card-body p-4 text-center">
                
                <div class="row justify-content-center mb-5">
                    <div class="col-md-8 col-lg-5">
                        <label for="cas_log_select" class="form-label fw-bold text-muted small text-uppercase">Επιλογή Αρχείου Καταγραφής</label>
                        <select id="cas_log_select" class="form-select form-select-lg shadow-sm border-secondary text-center fw-semibold text-primary" onchange="load_selected_cas_log();">
                            ' . $options . '
                        </select>
                    </div>
                </div>
                
                <div id="cas_log_table_container" class="table-responsive text-start">
                    <div class="alert alert-secondary text-center py-5 rounded-3 border-0">
                        <i class="bi bi-arrow-up-circle fs-1 text-muted d-block mb-3"></i>
                        <h5 class="text-muted">Παρακαλώ επιλέξτε ένα αρχείο από τη λίστα για να προβάλετε τα δεδομένα συνδέσεων.</h5>
                    </div>
                </div>
                
            </div>
        </div>
    </div>';
    
    return $html;
}

// ΝΕΑ ΣΥΝΑΡΤΗΣΗ: Επιστρέφει μόνο το HTML του πίνακα
function get_cas_credentials_table($filename)
{
    // Ασφάλεια: Κρατάμε μόνο το όνομα του αρχείου (για αποφυγή directory traversal attack)
    $filename = basename($filename);
    if(empty($filename)) return '';
    
    $file_path = __DIR__ . '/sch_feedback/' . $filename;
    
    if (!file_exists($file_path)) {
        return '<div class="alert alert-danger text-center"><i class="bi bi-exclamation-triangle me-2"></i>Το αρχείο δεν βρέθηκε ('.$filename.').</div>';
    }
    
    $raw_content = file_get_contents($file_path);
    $xml_string = '<root xmlns:cas="http://www.yale.edu/tp/cas">' . $raw_content . '</root>';
    
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xml_string);
    
    if ($xml === false) {
        return '<div class="alert alert-danger text-center"><i class="bi bi-exclamation-triangle me-2"></i>Σφάλμα κατά την ανάλυση της δομής του αρχείου XML. Πιθανόν το αρχείο να είναι άδειο ή αλλοιωμένο.</div>';
    }
    
    $xml->registerXPathNamespace('cas', 'http://www.yale.edu/tp/cas');
    $successes = $xml->xpath('//cas:authenticationSuccess');
    
    if (empty($successes)) {
        return '<div class="alert alert-warning text-center"><i class="bi bi-info-circle me-2"></i>Δεν υπάρχουν εγγραφές επιτυχών συνδέσεων σε αυτό το αρχείο.</div>';
    }
    
    $html = '
    <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i> Κάντε κλικ σε οποιαδήποτε σειρά του πίνακα για να δείτε το σύνολο των απεσταλμένων στοιχείων από το CAS.</p>
    <table id="cas_log_table" class="table table-striped table-hover table-bordered align-middle w-100">
        <thead class="table-light">
            <tr>
                <th class="text-center" style="width: 50px;">Α/Α</th>
                <th>Χρήστης (UID)</th>
                <th>Ονοματεπώνυμο / Ιδιότητα</th>
                <th>Στοιχεία Επικοινωνίας (ΑΜ/Mail)</th>
                <th>Υπηρεσία / Τοποθεσία</th>
                <th class="text-center">Ημερομηνία & Ώρα</th>
            </tr>
        </thead>
        <tbody>';
        
    $aa = 1;
    $successes = array_reverse($successes); 
    
    foreach ($successes as $entry) {
        $user = (string)$entry->children('cas', true)->user;
        $attrs = $entry->children('cas', true)->attributes->children('cas', true);
        
        $sn = isset($attrs->sn) ? (string)$attrs->sn : '';
        $givenName = isset($attrs->givenName) ? (string)$attrs->givenName : '';
        $title = isset($attrs->title) ? (string)$attrs->title : '';
        $mail = isset($attrs->mail) ? (string)$attrs->mail : '';
        $emp_num = isset($attrs->employeenumber) ? (string)$attrs->employeenumber : '';
        $client_ip = isset($attrs->clientIpAddress) ? (string)$attrs->clientIpAddress : '';
        $auth_date = isset($attrs->authenticationDate) ? (string)$attrs->authenticationDate : '';
        $ou = isset($attrs->ou) ? (string)$attrs->ou : '';
        
        if ($auth_date) {
            $date_obj = date_create($auth_date);
            $formatted_date = $date_obj ? date_format($date_obj, 'd/m/Y - H:i:s') : $auth_date;
        } else {
            $formatted_date = '-';
        }
        
        $fullname = trim($sn . ' ' . $givenName);
        if (empty($fullname)) { $fullname = "Δεν ορίστηκε"; }
        
        // Αναλυτικός πίνακας 
        $details_html = '<div class="table-responsive"><table class="table table-sm table-striped table-bordered text-start mb-0" style="font-size:0.85rem;">';
        $details_html .= '<thead class="table-dark"><tr><th style="width:35%;">Ιδιότητα (CAS Tag)</th><th>Τιμή (Value)</th></tr></thead><tbody>';
        $details_html .= '<tr><td class="fw-bold text-primary">cas:user</td><td><span class="badge bg-secondary">' . htmlspecialchars($user) . '</span></td></tr>';
        
        foreach ($attrs as $nodeName => $nodeValue) {
            $val_str = trim((string)$nodeValue);
            if ($nodeName == 'authenticationDate' && $val_str) {
                $d_obj = date_create($val_str);
                if ($d_obj) { $val_str = date_format($d_obj, 'd/m/Y - H:i:s'); }
            }
            $details_html .= '<tr><td class="fw-bold text-muted">cas:' . htmlspecialchars($nodeName) . '</td><td class="text-wrap" style="word-break:break-all;">' . htmlspecialchars($val_str) . '</td></tr>';
        }
        $details_html .= '</tbody></table></div>';
        
        $base64_details = base64_encode($details_html);
        
        $html .= '
        <tr onclick="view_cas_details(\''.$base64_details.'\');" style="cursor:pointer;" class="hover-highlight" title="Κάντε κλικ για προβολή όλων των στοιχείων">
            <td class="text-center fw-bold text-muted">'.$aa++.'</td>
            <td><span class="badge bg-secondary fs-6">'.$user.'</span></td>
            <td>
                <div class="fw-bold text-primary">'.$fullname.'</div>
                <div class="small text-muted">'.$title.'</div>
            </td>
            <td>
                <div class="small"><strong>ΑΜ:</strong> '.($emp_num ?: '-').'</div>
                <div class="small"><i class="bi bi-envelope text-secondary me-1"></i>'.$mail.'</div>
            </td>
            <td>
                <div class="small fw-semibold">'.$ou.'</div>
                <div class="small text-muted"><i class="bi bi-laptop me-1"></i>IP: '.$client_ip.'</div>
            </td>
            <td class="text-center font-monospace small align-middle">'.$formatted_date.'</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
        
    return $html;
}


// ##############################################################
// ########## DATABASE management functions #####################
// ##############################################################
function db_connect($host = null, $user = null, $pass = null, $db = null) {
    global $srv_conf;
    
    // Αν δεν δοθούν παράμετροι, παίρνει από το $srv_conf
    $h = $host ?? $srv_conf['DataBase']['host'];
    $u = $user ?? $srv_conf['DataBase']['username'];
    $p = $pass ?? $srv_conf['DataBase']['password'];
    $d = $db   ?? $srv_conf['DataBase']['Database Name'];

    $conn = @mysqli_connect($h, $u, $p, $d);
    
    if (!$conn) {
        return false;
    }
    mysqli_set_charset($conn, "utf8");
    return $conn;
}

function db_export_schema($conn, $filename = __DIR__.'/data/db_structure.json') {
    $structure = [];
    $tables = mysqli_query($conn, "SHOW TABLES");
    
    while ($row = mysqli_fetch_array($tables)) {
        $table = $row[0];
        $res = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
        $create = mysqli_fetch_array($res);
        $structure[$table] = $create[1];
    }
    
    return file_put_contents($filename, json_encode($structure, JSON_PRETTY_PRINT));
}

function db_sync_schema($conn, $filename = __DIR__.'/data/db_structure.json') {
    if (!file_exists($filename)) return "Δεν βρέθηκε αρχείο δομής.";
    
    // Έλεγχος αν υπάρχουν πίνακες
    $check = mysqli_query($conn, "SHOW TABLES");
    if (mysqli_num_rows($check) > 0) {
        return "Η βάση δεν είναι άδεια, η κατασκευή παραλείπεται.";
    }
    
    $structure = json_decode(file_get_contents($filename), true);
    
    foreach ($structure as $table => $sql) {
        if (!mysqli_query($conn, $sql)) {
            return "Σφάλμα στον πίνακα $table: " . mysqli_error($conn);
        }
    }
    
    return "Η δομή της βάσης κατασκευάστηκε επιτυχώς!";
}

// ##############################################################
// ########## END DATABASE management functions #################
// ##############################################################

/**
 * Διαβάζει το global $srv_conf, δημιουργεί ένα srv_conf.blank με κενές τιμές,
 * αλλά ΔΙΑΤΗΡΕΙ τις τιμές του applications_permanent.
 */
function generate_blank_srv_conf() {
    global $srv_conf;

    // Έλεγχος αν το srv_conf υπάρχει και είναι πίνακας
    if (empty($srv_conf) || !is_array($srv_conf)) {
        return "Σφάλμα: Το srv_conf είναι άδειο ή δεν έχει φορτωθεί σωστά.";
    }

    // Αναδρομική συνάρτηση για το άδειασμα των τιμών
    $empty_values = function($arr) use (&$empty_values) {
        $result = [];
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $result[$key] = $empty_values($val);
            } else {
                $result[$key] = '';
            }
        }
        return $result;
    };

    // 1. Δημιουργία του άδειου πίνακα
    $blank_conf = $empty_values($srv_conf);

    // 2. ΕΞΑΙΡΕΣΗ: Επαναφορά του applications_permanent από το αρχικό srv_conf
    if (isset($srv_conf['applications_permanent'])) {
        $blank_conf['applications_permanent'] = $srv_conf['applications_permanent'];
    }
    if (isset($srv_conf['Header_and_Footer'])) {
        $blank_conf['Header_and_Footer'] = $srv_conf['Header_and_Footer'];
    }
    if (isset($srv_conf['Main Users'])) {
        $blank_conf['Main Users'] = $srv_conf['Main Users'];
    }        

    // 3. Ορισμός ονόματος και διαδρομής αρχείου
    $filename = __DIR__ . '/data/srv_conf.blank';

    // 4. Αποθήκευση σε JSON
    $data_to_store = json_encode($blank_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // 5. Εγγραφή στο αρχείο
    if (file_put_contents($filename, $data_to_store, LOCK_EX)) {
        return "Επιτυχία: Το αρχείο srv_conf.blank δημιουργήθηκε (με διατήρηση των applications_permanent).";
    } else {
        return "Σφάλμα: Δεν ήταν δυνατή η εγγραφή του αρχείου.";
    }
}
