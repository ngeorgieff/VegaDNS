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




if(isset($_REQUEST['name'])) {
    $smarty->assign('name', $_REQUEST['name']);
} else {
    if(isset($_REQUEST['domain']))
        $smarty->assign('name', $_REQUEST['domain']);
}

// IpV6 condition
if($use_ipv6 == 'TRUE') {
    if($_REQUEST['mode'] == 'records') {
        $typearray = array(
		'A','AAAA','AAAA+PTR','NS','MX','PTR','TXT','CNAME','SRV');
    } else if ($_REQUEST['mode'] == 'default_records') {
        $typearray = array(
		'A','AAAA','AAAA+PTR','NS','MX','TXT','CNAME','SRV');
    }
} else {
    if($_REQUEST['mode'] == 'records') {
        $typearray = array('A','NS','MX','PTR','TXT','CNAME','SRV');
    } else if ($_REQUEST['mode'] == 'default_records') {
        $typearray = array('A','NS','MX','TXT','CNAME','SRV');
    }
}
$smarty->assign('typearray', $typearray);

if(isset($_REQUEST['type']))
    $smarty->assign('type_selected', $_REQUEST['type']);
if(isset($_REQUEST['address']))
    $smarty->assign('address', $_REQUEST['address']);

if(isset($_REQUEST['distance'])) {
    $smarty->assign('distance', $_REQUEST['distance']);
} else {
    $smarty->assign('distance', 0);
}

if(isset($_REQUEST['weight'])) {
    $smarty->assign('weight', $_REQUEST['weight']);
} else {
    $smarty->assign('weight', '');
}

if(isset($_REQUEST['port'])) {
    $smarty->assign('port', $_REQUEST['port']);
} else {
    $smarty->assign('port', '');
}



if(isset($_REQUEST['ttl'])) {
    $smarty->assign('ttl', $_REQUEST['ttl']);
} else {
    $smarty->assign('ttl', 3600);
}

$smarty->display('add_record_form.tpl');
