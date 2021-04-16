<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 4.0.0                                                |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2017 Issabel Foundation                                |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | The Initial Developer is Issabel Foundation                          |
  +----------------------------------------------------------------------+
*/

require_once "../../libs/misc.lib.php";
$module_name=basename(getcwd());
$documentRoot = $_SERVER["DOCUMENT_ROOT"];
include_once "$documentRoot/libs/paloSantoDB.class.php";
include_once "$documentRoot/libs/paloSantoACL.class.php";
session_name("issabelSession");
session_start();
$issabel_user = (isset($_SESSION["issabel_user"]))?$_SESSION["issabel_user"]:null;
$pDB = new paloDB("sqlite3:////var/www/db/acl.db");
$pACL = new paloACL($pDB);
$isUserAuth = $pACL->isUserAuthorized($issabel_user,"access",$module_name);
if(!$isUserAuth) { 
    echo '{"success":false, "message":"unauthorized"}';
    die();
}

$user = isset($_SESSION['issabel_user'])?$_SESSION['issabel_user']:"";
$extension = $pACL->getUserExtension($user);

$isAdmin = ($pACL->isUserAdministratorGroup($user) !== FALSE);

if($extension=='') {
    if($isAdmin) {
        echo '{"success":false, "message":"extension not associated"}';
    } else {
        echo '{"success":false, "message":"extension not associated unauthorized"}';
    }
    die();
}

$dsnAsterisk = generarDSNSistema('asteriskuser', 'asterisk','/var/www/html/');
$pDB = new paloDB($dsnAsterisk);
$query = "SELECT `name` FROM users WHERE extension='$extension'";
$row = $pDB->getFirstRowQuery($query, false, array());
$nombre = $row[0];
$query = "SELECT `data` FROM sip WHERE keyword='secret' AND id='$extension'";
$row = $pDB->getFirstRowQuery($query, false, array());
$secret = $row[0];


echo '{"success":true, "extension": "'.$extension.'","name":"'.$nombre.'", "secret":"'.$secret.'"}';
unset($_SESSION);
session_commit();
