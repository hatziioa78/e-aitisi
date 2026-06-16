<?php

// Main pivot php for selections

include(__DIR__.'/functions.php'); // main functions 

if(isset($_POST['selection']))
{

    switch ($_POST['selection'])
    {
        case 1:
            echo 'test 1';
            break;
        case 10:
            echo check_login($_POST['afm'],$_POST['amka']);
            break;
        case 15:
            echo show_firstlogon_page();
            break;            
        case 20:
            echo show_usersdata();
            break;    
        case 30:
            echo new_applications_menu();
            break;   
        case 40:
            echo applications_history();
            break;    
        case 50:
            echo show_usersdata4manager();
            break;    
        case 51:
            echo show_snames($_POST['letter']);
            break;    
        case 52:
            echo show_managers_applications();
            break;
        case 54:
            echo manager_connect2user($_POST['afm']);
            break;  
        case 55:
            echo manager_return();
            break;  
        case 57:
            echo show_managers_history();
            break;
        case 60:
            echo show_logs_page();
            break;
        case 61:
            echo read_log_file($_POST['logfile']);
            break;      
        case 70:
            echo new_permanent_application($_POST['id'],$_POST['db_application_id']);
            break;   
        case 71:
            echo isValidIBAN($_POST['iban']);
            break; 
        case 73:
            echo show_managers_permanent_application($_POST['afm'],$_POST['db_application_id']);
            break;
        case 75:
            echo assign_protocol2permanent_application($_POST['id'],$_POST['db_application_id'],json_decode($_POST['protocoldata'],true));
            break;
        case 80:
            echo save_permanent_application(json_decode($_POST['appdata'],true));
            break;  
        case 84:
            echo delete_permanent_application_protocol($_POST['db_application_id']);
            break;
        case 85:
            echo delete_permanent_application($_POST['id'],$_POST['afm'],$_POST['db_entry']);
            break;
        case 86:
            echo check_if_file_exist($_POST['filename']);
            break;
        case 87:
            echo delete_file($_POST['filename']);
            break;
        case 88:
            tcpdfit($_POST['htmlpdf']); // DOWNLOAD PDF
            break;
        case 90:
            echo sendmail_protocoled_application($_POST['db_application_id']);
            break;
        case 91:
            echo sendmail_protocol2application($_POST['db_application_id']);
            break;
        case 94:
            echo settings_srvconf();
            break;
        case 95:
            echo settings_page();
            break;
        case 96:
            echo change_srvconf($_POST['srvconf_name'],$_POST['srvconf_data']);
            break;
        case 97:
            echo settings_time_limits();
            break;
        case 98:
            echo settings_schools_table();
            break;
        case 981:
            echo save_school_entry($_POST['action'], $_POST['old_code'], $_POST['code'], $_POST['name'], $_POST['exclusions']);
            break;
        case 982:
            echo delete_school_entry($_POST['code']);
            break;     
        case 983:
            echo get_application_exclusions_view($_POST['app_id']);
            break;
        case 984:
            echo toggle_school_exclusion($_POST['app_id'], $_POST['code'], $_POST['action']);
            break;  
        case 99:
            echo succeded_login_cards_table();
            break;
        case 991:
            echo get_cas_credentials_table($_POST['filename']);
            break;
        case 100:
            echo $htmlout->choose_schools_boxes($_POST['div_container_id'],$_POST['app_id']); // επιλογή σχολείων με show_message για Υπεραριθμους
            break;

        case 110:
            echo save_time_limits(json_decode($_POST['time_limits'],true));
            break;
        case 120:
            session_start();
            $_SESSION=$_POST['sessionID'];
            $sessionID=session_id();
            session_write_close();
            //echo show_array($_POST['sessionID']);
            echo 1;
            break;
        case 121: // depricated now using logout.php 
            disconnect();
            break;
        case 130:
            echo excel_app100();
            break;
        case 131:
            echo excel_app101();
            break;
        case 132:
            echo excel_app102();
            break;
        case 133:
            echo excel_app103();
            break;                
        case 134:
            echo excel_app104();
            break;            
        case 140:
            echo show_managers_excel_downloads();
            break;
        default:
            echo 'No selection set';
            break;
    }
}
