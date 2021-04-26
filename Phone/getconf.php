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

$module_name  = basename(getcwd());

require_once "../../libs/misc.lib.php";
include_once "../../modules/address_book/libs/paloSantoAdressBook.class.php";
include_once "../../libs/paloSantoDB.class.php";
include_once "../../libs/paloSantoACL.class.php";
include_once "../../modules/myex_config/libs/paloSantoMyExtension.class.php";

session_name("issabelSession");
session_start();

$issabel_user = (isset($_SESSION["issabel_user"]))?$_SESSION["issabel_user"]:null;
$pDB          = new paloDB("sqlite3:////var/www/db/acl.db");
$pACL         = new paloACL($pDB);
$id_user      = $pACL->getIdUser($_SESSION["issabel_user"]);
$isUserAuth   = $pACL->isUserAuthorized($issabel_user,"access",$module_name);
$dsnAsterisk  = generarDSNSistema('asteriskuser', 'asterisk','/var/www/html/');

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

if(isset($_POST['action'])) {
   if($_POST['action']=="do_not_disturb") {
       $enableDND = $_POST['value'];
       $pMyExtension = new paloSantoMyExtension();
       $pMyExtension->AMI_OpenConnect();
       $statusDND  = $pMyExtension->setConfig_DoNotDisturb($enableDND,$extension);
       $pMyExtension->AMI_CloseConnect();
       die();
   }
}

$pMyExtension = new paloSantoMyExtension();
$pMyExtension->AMI_OpenConnect();
$statusDND       = $pMyExtension->getConfig_DoNotDisturb($extension);
$pMyExtension->AMI_CloseConnect();

$pDB   = new paloDB("sqlite3:////var/www/db/address_book.db");
$padress_book = new paloAdressBook($pDB);
$external = $padress_book->getAddressBook(NULL,NULL,NULL,NULL,FALSE,$id_user);

$final=array();
foreach($external as $data) {
    $info = array("ExtensionNumber"=>'',"MobileNumber"=>$data["cell_phone"],"ContactNumber1"=>$data['telefono'],"ContactNumber2"=>$data['home_phone'],"DisplayName"=>$data['name'].' '.$data['lastname'], "Email"=>$data['email'], "Description"=> $data['company'], "Type"=>"contact");
    $final[] = $info;
}


$internal = $padress_book->getDeviceFreePBX_Completed($dsnAsterisk, 10000,0,'','');
foreach($internal as $idx=>$data) {
    if($data['id']==$extension) continue;
    $info = array("ExtensionNumber"=>$data['id'],"DisplayName"=>$data['description'],"Email"=>$data['email'],"Description"=>$data['description'],"Type"=>"extension");
    $final[] = $info;
}

$buddies = json_encode($final);

$pDB = new paloDB($dsnAsterisk);
$query = "SELECT `name` FROM users WHERE extension='$extension'";
$row = $pDB->getFirstRowQuery($query, false, array());
$nombre = $row[0];
$query = "SELECT `data` FROM sip WHERE keyword='secret' AND id='$extension'";
$row = $pDB->getFirstRowQuery($query, false, array());
$secret = $row[0];


echo '{"success":true, "extension": "'.$extension.'","name":"'.$nombre.'", "secret":"'.$secret.'", "dnd": "'.$statusDND.'" ,"buddies":'.$buddies.'}';
unset($_SESSION);
session_commit();
