<?php
if (
    !empty($_POST['primaryLink']) &&
    !empty($_POST['linkCount']) &&
    !empty($_POST['linkLen'])
) {
    $DBH = new PDO("sqlite:link.db");
    $STH = $DBH->query('CREATE TABLE if not exists links (id INTEGER PRIMARY KEY, primaryLink TEXT, secondaryLink TEXT)');

    $result = array();
    for ($i = 0; $i < $_POST['linkCount']; $i++)
    {
        $link = randomString($_POST['linkLen']);
        $result[] = $_SERVER['HTTP_HOST'] . '/' . $link;

        $STH = $DBH->prepare("INSERT INTO links (primaryLink, secondaryLink) VALUES (:primaryLink, :secondaryLink)");
        $STH->execute(array('primaryLink' => $_POST['primaryLink'], 'secondaryLink' => $link));
    }

    echo json_encode($result);
    exit;
}


if (!empty($_GET['url'])) {
    $DBH = new PDO("sqlite:link.db");
    $STH = $DBH->prepare("SELECT * FROM links WHERE secondaryLink = :url");
    $STH->execute(array('url' => $_GET['url']));
    $STH->setFetchMode(PDO::FETCH_OBJ);
    $item = $STH->fetch();

    if (!empty($item->primaryLink)) {
        header('Location: ' . $item->primaryLink);
    } else {
        header('Location: http://' . $_SERVER['HTTP_HOST']);
    }
}

/**
 * /redirect.php?check=1
 */
if (!empty($_GET['check'])) {
    $DBH = new PDO("sqlite:link.db");

    $STH = $DBH->query('CREATE TABLE if not exists test (id INTEGER PRIMARY KEY, message TEXT)');
    $STH = $DBH->query("INSERT INTO test (message) VALUES ('test')");

    $STH = $DBH->prepare("SELECT * FROM test WHERE id = 1");
    $STH->execute();
    $STH->setFetchMode(PDO::FETCH_OBJ);
    $item = $STH->fetch();

    if (!empty($item->message)) {
        echo "Sqlite OK! <br /> \n";
    } else {
        echo "Sqlite FAIL! <br /> \n";
    }

    if (is_file(__DIR__ . '/.htaccess')) {
        echo ".htaccess find in directory FAIL! <br /> \n";
    } else {
        echo ".htaccess not found in directory OK! <br /> \n";
    }
}

/**
 * /redirect.php?create_htaccess=1
 */
if (!empty($_GET['create_htaccess'])) {
    file_put_contents(__DIR__ . '/.htaccess', "Options +FollowSymLinks
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule (.*) /redirect.php?url=$1 [R=301,L]");

    if (is_file(__DIR__ . '/.htaccess')) {
        echo ".htaccess is created OK! <br /> \n";
    } else {
        echo ".htaccess not created FAIL! <br /> \n";
    };
}


function randomString($count = 4)
{
    $characters = 'abcdefghijklmnopqrstuvwxyz';
    $string = '';
    for ($i = 0; $i < $count; $i++) {
        $string.= $characters[rand(0, (strlen($characters) - 1))];
    }
    return $string;
}
