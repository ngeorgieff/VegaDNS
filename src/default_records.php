<?php


/*
 * 
 * VegaDNS - DNS Administration Tool for use with djbdns
 * 
 * CREDITS:
 * Written by Bill Shupp
 * <bill@merchbox.com>
 * 
 * LICENSE:
 * This software is distributed under the GNU General Public License
 * Copyright 2003-2006, MerchBox.Com
 * see COPYING for details
 * 
 */ 

if(!ereg(".*/index.php$", $_SERVER['PHP_SELF'])) {
    header("Location:../index.php");
    exit;
}





// Make sure this is a group_admin or a senior_admin

if($user_info['account_type'] == 'user') {
    setmsg_err("Error: you do not have permission to edit default resource records");
    $smarty->display('header.tpl');
    $smarty->display('footer.tpl');
    exit;
}

if(!isset($_REQUEST['record_mode']) || $_REQUEST['record_mode'] == 'delete_cancelled') {

    if(isset($_REQUEST['record_mode']) && $_REQUEST['record_mode'] == 'delete_cancelled') {
        // Set cancelled message
        set_msg("Delete Cancelled");
    }

    // Get records list
    $sa_q = "select * from default_records where default_type='system'";
    $ga_q = "select * from default_records where group_id='".$user_info['user_id']."'";
    if($user_info['account_type'] == 'senior_admin') {
        $result = $db->Execute($sa_q) or die($db->ErrorMsg());
    } else {
        $result = $db->Execute($ga_q) or die($db->ErrorMsg());
        if($result->RecordCount() == 0)
            $result = $db->Execute($sa_q) or die($db->ErrorMsg());
    }

    // Build records data structure
    $counter = 0;
    while ($row = $result->FetchRow()) {
        $records[$counter]['record_id'] = $row['record_id'];
        $records[$counter]['host'] = $row['host'];
        $records[$counter]['type'] = $row['type'];
        $records[$counter]['val'] = $row['val'];
        $records[$counter]['distance'] = $row['distance'];
	    $records[$counter]['weight'] = $row['weight'];
	    $records[$counter]['port'] = $row['port'];
        $records[$counter]['ttl'] = $row['ttl'];
        $counter++;
    }

    // Get SOA
    while((list($num,$array) = each($records)) && !isset($soa)) {
        if($array['type'] == 'S') $soa = $array; 
    }
    reset($records);

    // Parse SOA
    if(isset($soa)) {
        $soa_array = parse_soa($soa);
    } else {
        $tldemail = "hostmaster.$domain";
        while((list($num,$array) = each($records)) && !isset($tldhost)) {
            if($array['type'] == 'N') $soa_array['tldhost'] = $array['host']; 
        }
        $soa_array['serial'] = "default";
        $soa_array['refresh'] = 16384;
        $soa_array['retry'] = 2048;
        $soa_array['expire'] = 1048576;
        $soa_array['minimum'] = 2560;
    }

    $counter = 0;
    while(list($key,$array) = each($records)) {
        $type = get_type($array['type']);
        if($type != 'SOA') {
            $out_array[$counter]['host'] = $array['host'];
            $out_array[$counter]['type'] = $type;
            $out_array[$counter]['val'] = $array['val'];
            if($type == 'MX' || $type == 'SRV') {
                $out_array[$counter]['distance'] = $array['distance'];
            } else {
                $out_array[$counter]['distance'] = 'n/a';
            }
	    if($type == 'SRV') {
                $out_array[$counter]['weight'] = $array['weight'];
		$out_array[$counter]['port'] = $array['port'];
            } else {
                $out_array[$counter]['weight'] = 'n/a';
		$out_array[$counter]['port'] = 'n/a';
            }

            $out_array[$counter]['ttl'] = $array['ttl'];
            $out_array[$counter]['delete_url'] = "$base_url&mode=default_records&record_mode=delete&record_id=".$array['record_id'];
            $counter++;
        }
    }

    $smarty->assign('edit_soa_url', "$base_url&mode=default_records&record_mode=edit_soa");
    $smarty->assign('add_record_url', "$base_url&mode=default_records&record_mode=add_record");
    $smarty->assign('view_log_url', "$base_url&mode=default_records&record_mode=view_log");
    $smarty->assign('soa_array', $soa_array);
    if(isset($out_array))
    	$smarty->assign('out_array', $out_array);

    $smarty->display('header.tpl');
    $smarty->display('list_default_records.tpl');
    $smarty->display('footer.tpl');
    exit;

} else if($_REQUEST['record_mode'] == 'add_record') {

    $smarty->display('header.tpl');
    require('src/add_record_form.php');
    $smarty->display('footer.tpl');
    exit;

} else if($_REQUEST['record_mode'] == 'add_record_now') {

    // verify record to be added
    $result = verify_record($_REQUEST['name'],$_REQUEST['type'],$_REQUEST['address'],$_REQUEST['distance'],$_REQUEST['weight'], $_REQUEST['port'],$_REQUEST['ttl']);
    if($result != 'OK') {
        set_msg_err($result);
        $smarty->display('header.tpl');
        require('src/add_record_form.php');
        $smarty->display('footer.tpl');
        exit;
    } else {


        // Get default_type
        if($user_info['account_type'] == 'senior_admin') {
            $default_type = 'system';
        } else {
            $default_type = 'group';
        }

        // Add domain to 'name'
        if(!eregi("^.*\.(DOMAIN)$", $_REQUEST['name']) && !eregi("^(DOMAIN)$", $_REQUEST['name'])
            && $_REQUEST['type'] != 'PTR') {
            if(strlen($_REQUEST['name']) > 0) {
                $name = $_REQUEST['name'].".DOMAIN";
            } else {
                $name = 'DOMAIN';
            }
        } else {
            $name = $_REQUEST['name'];
        }

        if($_REQUEST['type'] == 'A') {
            $q = "insert into default_records 
            (group_id,host,type,val,ttl,default_type) values(
            '".$user_info['user_id']."',
            '$name',
            '".set_type($_REQUEST['type'])."',
            ".$db->Quote($_REQUEST['address']).",
            '".$_REQUEST['ttl']."',
            '$default_type')";
        } else if($_REQUEST['type'] == 'MX') {
            if(!ereg("\..+$", $_REQUEST['address'])) {
                $mxaddress = $_REQUEST['address'].".DOMAIN";
            } else {
                $mxaddress = $_REQUEST['address'];
            }
            $q = "insert into default_records 
            (group_id,host,type,val,distance,ttl,default_type) values(
            '".$user_info['user_id']."',
            '$name',
            '".set_type($_REQUEST['type'])."',
            ".$db->Quote($mxaddress).",
            ".$db->Quote($_REQUEST['distance']).",
            '".$_REQUEST['ttl']."',
            '$default_type')";
        } else if($_REQUEST['type'] == 'NS') {
            $q = "insert into default_records 
            (group_id,host,type,val,ttl,default_type) values(
            '".$user_info['user_id']."',
            '$name',
            '".set_type($_REQUEST['type'])."',
            ".$db->Quote($_REQUEST['address']).",
            '".$_REQUEST['ttl']."',
            '$default_type')";
        } else if($_REQUEST['type'] == 'CNAME') {
            $q = "insert into default_records 
            (group_id,host,type,val,ttl,default_type) values(
            '".$user_info['user_id']."',
            '$name',
            '".set_type($_REQUEST['type'])."',
            ".$db->Quote($_REQUEST['address']).",
            '".$_REQUEST['ttl']."',
            '$default_type')";
        } else if($_REQUEST['type'] == 'TXT') {
            $q = "insert into default_records 
            (group_id,host,type,val,ttl,default_type) values(
            '".$user_info['user_id']."',
            '$name',
            '".set_type($_REQUEST['type'])."',
            ".$db->Quote($_REQUEST['address']).",
            '".$_REQUEST['ttl']."',
            '$default_type')";
	} else if ($_REQUEST['type'] == 'SRV') {
	    if(!ereg("\..+$", $_REQUEST['address'])) {
                $srvaddress = $_REQUEST['address'].".DOMAIN";
            } else {
                $srvaddress = $_REQUEST['address'];
            }

	    $q = "insert into default_records
	    (group_id,host,type,val,distance,weight,port,ttl,default_type) values (
	    '".$user_info['user_id']."',
            '$name',
            '".set_type($_REQUEST['type'])."',
            ".$db->Quote($srvaddress).",
            ".$db->Quote($_REQUEST['distance']).",
	    ".$db->Quote($_REQUEST['weight']).",
	    ".$db->Quote($_REQUEST['port']).",
            '".$_REQUEST['ttl']."',
            '$default_type')";
        }
        $db->Execute($q) or die($db->ErrorMsg());
        set_msg("Record added successfully!");
        header("Location: $base_url&mode=default_records");
        exit;
    }

} else if($_REQUEST['record_mode'] == 'delete') {

    // Get record info
    $q = "select * from default_records where record_id='".$_REQUEST['record_id']."' limit 1";
    $result = $db->Execute($q) or die($db->ErrorMsg());
    $row = $result->FetchRow();

    $smarty->assign('type', get_type($row['type']));
    $smarty->assign('host', $row['host']);
    $smarty->assign('cancel_url', "$base_url&mode=default_records&record_mode=delete_cancelled");
    $smarty->assign('delete_url', "$base_url&mode=default_records&record_mode=delete_now&record_id=".$row['record_id']);
    $smarty->display('header.tpl');
    $smarty->display('delete_record_confirm.tpl');
    $smarty->display('footer.tpl');
    exit;

} else if($_REQUEST['record_mode'] == 'delete_now') {

    // make sure the record_id was given
    if(!isset($_REQUEST['record_id'])) {
        set_msg_err("Error: no record_id");
        $smarty->display('header.tpl');
        $smarty->display('footer.tpl');
        exit;
    }

    if($user_info['account_type'] == 'senior_admin') {
        $q = "delete from default_records where record_id='".$_REQUEST['record_id']."'";
    } else {
        $q = "delete from default_records where record_id='".$_REQUEST['record_id']."' and group_id='".$user_info['user_id']."'";
    }
    $db->Execute($q) or die($db->ErrorMsg());
    set_msg("Record deleted successfully");
    header("Location: $base_url&mode=default_records");
    exit;

} if($_REQUEST['record_mode'] == 'edit_soa') {

    // Get Current SOA information

    // Get records list
    $sa_q = "select * from default_records where default_type='system' and type='S'";
    $ga_q = "select * from default_records where group_id='".$user_info['user_id']."' and type='S'";
    if($user_info['account_type'] == 'senior_admin') {        
        $result = $db->Execute($sa_q) or die($db->ErrorMsg());
    } else {        
        $result = $db->Execute($ga_q) or die($db->ErrorMsg());
        if($result->RecordCount() == 0)
            $result = $db->Execute($sa_q) or die($db->ErrorMsg());
    }

    $row = $result->FetchRow();
    $soa = parse_soa($row);

    // Edit SOA Menu
    $smarty->display('header.tpl');
    require('src/edit_default_soa_form.php');
    $smarty->display('footer.tpl');
    exit;

} if($_REQUEST['record_mode'] == 'edit_soa_now') {


    // Check email
    if($_REQUEST['tldemail'] == "") {
        set_msg_err("Error: missing contact address");
        $smarty->display('header.tpl');
        require('src/edit_default_soa_form.php');
        $smarty->display('footer.tpl');
        exit;
    }
    
    // See if this group_admin has an existing soa
    if($user_info['account_type'] == 'group_admin') {
        $default_type = 'group';
        $q = "select record_id from default_records where type='S' and group_id='".$user_info['user_id']."' limit 1";
        $result = $db->Execute($q) or die($db->ErrorMsg());
        if($result->RecordCount() == 0) {
            $new_soa = 1;
        } else {
            $new_soa = 0;
            $row = $result->FetchRow();
            $id = $row['record_id'];
        }
    } else {
        $default_type = 'system';
        $q = "select record_id from default_records where type='S' and default_type='system' limit 1";
        $result = $db->Execute($q) or die($db->ErrorMsg());
        $row = $result->FetchRow();
        $id = $row['record_id'];
        $new_soa = 0;
    }
    // Build array from $_REQUEST
    $array['host'] = $_REQUEST['tldemail'].':'.$_REQUEST['tldhost'];
    $array['val'] = $_REQUEST['refresh'].':'.$_REQUEST['retry'].':'.$_REQUEST['expire'].':'.$_REQUEST['minimum'];
    $array['ttl'] = $_REQUEST['ttl'];

    $return = parse_soa($array);

    // Build host and val fields
    $host = $return['tldemail'].':'.$return['tldhost'];
    $val = $return['refresh'].':'.$return['retry'].':'.$return['expire'].':'.$return['minimum'];

    // Update table
    if($new_soa == 1) {
        $q = "insert into default_records values(
            '',
            ".$user_info['user_id'].",
            '$host',
            'S',
            '$val',
            0,,,
            '".$_REQUEST['ttl']."',
            'group')";
    } else {
        $q = "replace into default_records set 
            record_id='$id',
            host='$host',
            type='S',
            val='$val',
            ttl='".$_REQUEST['ttl']."',
            default_type='$default_type',
            group_id='".$user_info['user_id']."'";
    }
    $db->Execute($q) or die($db->ErrorMsg().'<br>'.$q);

    // Display domain
    set_msg("Default SOA record updated successfully");
    header("Location: $base_url&mode=default_records");
    exit;

} else if($_REQUEST['record_mode'] == "view_log") {

    require('src/view_log.php');
    exit;
    
} else {

    die("Error: illegal records_mode");

}

?>
