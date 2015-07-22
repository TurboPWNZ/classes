<?php
global $user, $base_url;
$mysite = 'http://'.$_POST['host']; 
$mynid = $_POST['nid'];
$remoteip = $_SERVER['REMOTE_ADDR'];
$myid = 4990833;
$myguid = $_POST['guid'];
$mycookieid = (int)$_POST['ckid'];

$mylink = 'https://api.vk.com/method/likes.getList?type=sitepage&owner_id='.$myid.'&page_url='.$mysite.'?mypp='.$myguid;
//print $mylink;
$myjs1 = file_get_contents($mylink);
$myvk1 = json_decode($myjs1);
$mycc = $myvk1->response->users[0];

//print $mycc;

$mylink = 'https://api.vk.com/method/users.get?lang=ru&user_ids='.$mycc.'&fields=sex,bdate,city,country,photo_50,interests,domain,has_mobile,contacts,connections,activities';
    $myjs1 = file_get_contents($mylink);
    
    $myvkuser = json_decode($myjs1);

    $mycity = '';
    $mylink3 = 'https://api.vk.com/method/database.getCitiesById?lang=ru&city_ids='.$myvkuser->response[0]->city;
    $myjs3 = file_get_contents($mylink3);
    $myvkcity = json_decode($myjs3);
    $mycity = $myvkcity->response[0]->name;

    $userData = array('uid' => $myuid,
	  					'uid' => $myuid,
	  					'nid' => $mynid,
	  					'cookid' => $mycookieid,
	  					'vkid' => $mycc,
	  					'vkname' => $myvkuser->response[0]->first_name ? $myvkuser->response[0]->first_name : '',
	  					'lname' => $myvkuser->response[0]->last_name ? $myvkuser->response[0]->last_name : '',
	  					'sex' => $myvkuser->response[0]->sex,
	  					'domain' => $myvkuser->response[0]->domain ? $myvkuser->response[0]->domain : '',
	  					'bdate' => $myvkuser->response[0]->bdate ? $myvkuser->response[0]->bdate : '',
	  					'city' => $myvkuser->response[0]->city,
	  					'citytxt' => $mycity ? $mycity : '',
	  					'photo_50' => $myvkuser->response[0]->photo_50 ? $myvkuser->response[0]->photo_50 : '',
	  					'interests' => $myvkuser->response[0]->interests ? $myvkuser->response[0]->interests : '',
	  					'activities' => $myvkuser->response[0]->activities ? $myvkuser->response[0]->activities : '',
	  					'contacts' => $myvkuser->response[0]->contacts ? $myvkuser->response[0]->contacts : '',
	  					'remoteid' => $remoteip

	  	);
	?>
<?= file_put_contents('log.txt', $userData['vkname'] . ' ' . $userData['lname'] . ' ' . $userData['vkid'] . "\n", FILE_APPEND | LOCK_EX);?>
