<?php
define('API_KEY', 'zp3kitpic');

//echo file_get_contents(__DIR__ . '/cookies_tmp/r.txt'); exit;
if(is_dir(__DIR__ . "/cookies_tmp") === false) {
    mkdir(__DIR__ . "/cookies_tmp");
}

if(is_dir(__DIR__ . "/cookies_tmp") === false) {
    echo 'Error can`t create tmp folder!';
    exit;
}

if (!function_exists('curl_init')) {
    echo 'Curl can`t instance!';
    exit;
}

if (!file_put_contents(__DIR__ . '/cookies_tmp/t.txt', 't')) {
    echo 'Can`t write in tmp folder!';
    exit;
}

class Connector {

    const API_KEY = '552d8c605d4492869010f3cf59ec6102';

    private static $_login = '';

    private static $_password = '';

    private static $_loops = 0;

    private static $_maxLoops = 5;

    private $url = array(
        'login' => 'https://login.vk.com/?act=login',
        'mobile' => 'http://m.vk.com',
        'like' => 'http://vk.com/like.php',
        'photo' => 'http://m.vk.com/{photoID}',
        'photoLike' => 'http://m.vk.com/like?act=add&object=photo-{photoID}&from=photo-{photoID}&hash={hash}',
        'photoComment' => 'http://m.vk.com/{photoID}?act=post_comment&hash={hash}',
        'friend' => 'http://m.vk.com/{id}',
        'group' => 'http://m.vk.com/{id}',
        'wall' => 'http://m.vk.com/wall-{id}',
    );

    private static $_instance = false;

    public static function instance($l = false, $p = false)
    {
        if(self::$_instance === false) {
            self::$_instance = new self($l, $p);
        }

        return self::$_instance;
    }

    private function __construct($l, $p)
    {
        self::$_login = $l;
        self::$_password = $p;

        $content = $this->read($this->url['mobile']);

        if ($this->checkAuthorization($content) === false) {

            preg_match('/<form method="post" action="(.*)" novalidate>/', $content, $hash);

            $response = $this->connect($hash[1],
                array(
                    'email' => str_replace(' ', '+', self::$_login),
                    'pass' => str_replace(' ', '+', self::$_password),
                ),
				$this->url['mobile']
            );
			//file_put_contents(__DIR__ . '/cookies_tmp/r.txt', $response); exit;
            if ($this->checkAuthorization($response) === false) {
                $checkData = $this->checkPhoneConfirm($response, self::$_login);
                if ($checkData === false) {
                    $this->sendError('Login incorrect.');
                } else {
                    // Вводим суке телефон.
                    $this->sleep();
                    $response = $this->connect($this->url['mobile'] . $checkData['action'],
                        array(
                            'code' => $checkData['phone'],
                        )
                    );
                    //file_put_contents(__DIR__ . '/cookies_tmp/phone.txt', $response);
                    if ($this->checkAuthorization($response) === false) {
                        $this->sendError('Fail phone crack!');
                    }
                }
            }
            $this->sleep();
        }
    }

    /**
     * Проверяем не просит ли эта шлюха телефон!!!
     *
     * @param $content
     * @param $login
     *
     * @return array|boolean
     */
    protected function checkPhoneConfirm($content, $login)
    {
        preg_match_all('/<span class="field_prefix">(.*)<\/span>/', $content, $code);
        if (empty($code[1][0]) === false) {
            preg_match('/<form method="post" action="(.*)">/', $content, $action);
            $code_prefix = $code[1][0];
            $phone = str_replace($code_prefix, '', str_replace(' ', '+', $login));
            if (isset($code[1][1])) {
                $code_suffix = preg_replace ("/[^0-9\s]/","", $code[1][1]);
                $phone = substr($phone,0,-strlen($code_suffix));
            }

            return array(
                'phone' => $phone,
                'action' => $action[1],
            );
        } else {
            return false;
        }
    }

    /**
     * Лайкает записи со стены по id.
     *
     * @param $wallId
     * @param int $count
     */
    public function wallLike($wallId, $count = 10)
    {

        $response = $this->connect(
            $this->prepareUrl(
                $this->url['wall'],
                array(
                    '{id}' => $wallId
                )
            )
        );

        preg_match_all('/<a class="item_like _i" href="(.*)"><i class="i_like"><\/i>/', $response, $post);

        if (empty($post[1]) === false && is_array($post[1]) && count($post[1]) > 0) {
            foreach ($post[1] as $key => $link) {
                if (($key + 1) > $count) {break;}
                $this->connect(
                    $this->url['mobile'] . $link,
                    array()
                );
                $this->sleep();
            }
        }
    }

    /**
     * Лайкает фото по ID.
     */
    public function photoLike($photoID)
    {
        $response = $this->connect($this->prepareUrl(
            $this->url['photo'],
            array(
                '{photoID}' => $photoID
            )
        ));
//        file_put_contents('responce.html', $response); exit;
        preg_match('/<a href="\/like(.*)">Мне нравится<\/a>/', $response, $hash);
        if (empty($hash[1]) === false)
        {
            $link = '/like' . $hash[1];
        }
//        var_dump($link); exit;
//        preg_match('/<form action="\/photo-' .$photoID. '(.*)hash=(.*)" method="post">/', $response, $hash);

        if(empty($link) === false) {
            $this->connect(
                $this->url['mobile'] . $link,
                array(
                    '_ajax' => 1
                )
            );

            return true;
        } else {
            return false;
        }
    }

    /**
     * Пишет комент к фото по ID.
     */
    public function photoComment($photoID, $text)
    {
        $response = $this->connect($this->prepareUrl(
            $this->url['photo'],
            array(
                '{photoID}' => $photoID
            )
        ));
//        file_put_contents('responce.html', $response); exit;
        preg_match('/<form action="\/' .$photoID. '(.*)hash=(.*)" method="post">/', $response, $hash);
//        var_dump($hash); exit;
        if(empty($hash[2]) === false) {
            $hash = $hash[2];
            $response = $this->connect(
                $this->prepareUrl(
                    $this->url['photoComment'],
                    array(
                        '{photoID}' => $photoID,
                        '{hash}' => $hash,
                    )
                ),
                array(
                    '_ajax' => 1,
                    'message' => $text,
                )
            );
            $this->checkCaptcha($response, $photoID, 'photoComment', array('message' => $text));
        }
    }

    /**
     * Добавляет в друзья по ID.
     */
    public function addFriend($id)
    {
        $response = $this->connect($this->prepareUrl(
            $this->url['friend'],
            array(
                '{id}' => $id
            )
        ));

        preg_match('/<a class="button wide_button" href="\/friends(.*)">Добавить в друзья<\/a>/', $response, $addLink);

        if(empty($addLink[1]) === false) {
            $addLink = $addLink[1];
            $response = $this->connect(
                $this->url['mobile'] . '/friends' . $addLink,
                array(
					'message' => 'hello'
				)
            );
            $this->checkCaptcha($response, $id);
        }
    }

    /**
     * Пишет личное сообщение по ID.
     */
    public function writeFriend($id, $message)
    {
        $response = $this->connect($this->prepareUrl(
            $this->url['friend'],
            array(
                '{id}' => $id
            )
        ));

        //preg_match('/<a class="button wide_button" href="\/friends(.*)">Добавить в друзья<\/a>/', $response, $addLink);
        preg_match('/<a class="button wide_button" href="(.*)">Личное сообщение<\/a>/', $response, $writeLink);

        if(empty($writeLink[1]) === false) {
            $writeLink = $writeLink[1];
			$response = $this->read($this->url['mobile'] . $writeLink);
			
			preg_match('/<form id="write_form" action="(.*)" method="post">/', $response, $sendLink);
			if(empty($sendLink[1]) === false) {
				$sendLink = $sendLink[1];
				$response = $this->connect(
                $this->url['mobile'] . $sendLink,
					array(
						'message' => $message
					)
				);
				
				preg_match('/<div class="service_msg service_msg_ok">(.*)<\/div>/', $response, $sendMessage);
				if(empty($sendMessage[1]) === false) {
					echo json_encode(array('success' => $sendMessage[1])); exit;
				}
				//file_put_contents(__DIR__ . '/cookies_tmp/r.txt', $response); exit;
				$this->checkCaptcha($response, $id);
			}

            //$this->checkCaptcha($response, $id);
        }
    }

    /**
     * Добавляеться в группу по ID.
     */
    public function joinGroup($id)
    {
        $response = $this->connect($this->prepareUrl(
            $this->url['group'],
            array(
                '{id}' => $id
            )
        ));

        preg_match('/<a class="button wide_button" href="(.*)">Вступить в группу<\/a>/', $response, $addLink);
        if (empty($addLink[1])) {
            preg_match('/<a class="button wide_button" href="(.*)">Подписаться<\/a>/', $response, $addLink);
        }

        if(empty($addLink[1]) === false) {
            $addLink = $addLink[1];
            $this->connect(
                $this->url['mobile'] . $addLink,
                array()
            );
            return true;
        } else {
            return false;
        }
    }

    /**
     * Пишет на стене группы по ID.
     */
    public function wallPost($id, $message)
    {
        $response = $this->connect($this->prepareUrl(
            $this->url['group'],
            array(
                '{id}' => $id
            )
        ));

        preg_match('/<form action="(.*)" method="post">/', $response, $addLink);

        if(empty($addLink[1]) === false) {
            $addLink = $addLink[1];
            $this->connect(
                $this->url['mobile'] . $addLink,
                array(
                    'message' => $message
                )
            );
        }
    }

    private function connect($link, $post = null, $referer = false)
    {
//        echo 'POST of link ' . $link . ':';
//        var_dump($post);
		if (empty($referer)) {
			$referer = $link;
		}
        $ch = curl_init();
        $f = fopen(__DIR__ . '/cookies_tmp/request.txt', 'w');
        $header = array();
        $header[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4";
        $header[] = "Pragma: "; // browsers keep this blank.
        curl_setopt($ch, CURLOPT_URL,$link);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_HEADER, 1);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, sdch');
        // если ведется проверка HTTP User-agent, то передаем один из возможных допустимых вариантов:
        curl_setopt ($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.81 Safari/537.36');
        // елси проверятся откуда пришел пользователь, то указываем допустимый заголовок HTTP Referer:
        curl_setopt ($ch, CURLOPT_REFERER, $referer);
        curl_setopt ($ch, CURLOPT_VERBOSE, 1);
//        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt ($ch, CURLOPT_STDERR, $f);
        curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . "/cookies_tmp/" . "cookie".self::$_login.".txt");
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . "/cookies_tmp/" . "cookie".self::$_login.".txt");

        if($post !== null) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $response = $this->curl_exec_follow($ch);
        curl_close($ch);

        return $response;
    }

    // чтение страницы после авторизации
    private function read($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        // откуда пришли на эту страницу
//        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // елси проверятся откуда пришел пользователь, то указываем допустимый заголовок HTTP Referer:
        curl_setopt($ch, CURLOPT_REFERER, $url);
//        curl_setopt($ch, CURLOPT_VERBOSE, 1);

        //запрещаем делать запрос с помощью POST и соответственно разрешаем с помощью GET
        curl_setopt($ch, CURLOPT_POST, 0);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        //отсылаем серверу COOKIE полученные от него при авторизации
        curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . "/cookies_tmp/" . "cookie".self::$_login.".txt");
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . "/cookies_tmp/" . "cookie".self::$_login.".txt");
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, sdch');
        // если ведется проверка HTTP User-agent, то передаем один из возможных допустимых вариантов:
        curl_setopt ($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.81 Safari/537.36');

        $result = $this->curl_exec_follow($ch);

        curl_close($ch);

        return $result;
    }

    // чтение страницы после авторизации
    private function readCaptcha($url, $refer){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        // откуда пришли на эту страницу
//        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_REFERER, $refer);
//        $f = fopen('captcha.txt', 'w');
        $header = array();
        $header[] = "Accept: image/webp,*/*;q=0.8";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
        $header[] = "Host: m.vk.com";
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4";
        $header[] = "Content-Type: application/x-www-form-urlencoded";
        $header[] = "Pragma: "; // browsers keep this blank.
        $header[] = 'Expect:';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
//        curl_setopt($ch, CURLOPT_VERBOSE, 1);
//        curl_setopt ($ch, CURLOPT_STDERR, $f);
        //запрещаем делать запрос с помощью POST и соответственно разрешаем с помощью GET
        curl_setopt($ch, CURLOPT_POST, 0);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        //отсылаем серверу COOKIE полученные от него при авторизации
        curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . "/cookies_tmp/" . "cookie".self::$_login.".txt");
        curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . "/cookies_tmp/" . "cookie".self::$_login.".txt");
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, sdch');
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.81 Safari/537.36");

        $result = curl_exec($ch);

        curl_close($ch);

        return $result;
    }

    private function curl_exec_follow( $ch )
    {
        if (self::$_loops++ >= self::$_maxLoops)
        {
            self::$_loops = 0;
            return false;
        }
        $data = curl_exec($ch);
        $temp = $data;
        list($header, $data) = explode("\n\n", $data, 2);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http == 301 || $http == 302) {
            $matches = array();
            preg_match('/ocation:(.*?)\n/', $header, $matches);
            $url = @parse_url(trim(array_pop($matches)));
//            print_r($url);
            if (!$url)
            {
                self::$_loops = 0;
                return $data;
            }
            $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
            if (!$url['scheme'])
                $url['scheme'] = $last_url['scheme'];
            if (!$url['host'])
                $url['host'] = $last_url['host'];
            if (!$url['path'])
                $url['path'] = $last_url['path'];

            $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query']?'?'.$url['query']:'');
//            echo "\n redirect to ".$new_url;
            //@todo Возможна непредвиденая хуйня.
            curl_setopt($ch, CURLOPT_POST, 0);
            curl_setopt($ch, CURLOPT_URL, $new_url);
            return $this->curl_exec_follow($ch);
        } else {
            self::$_loops = 0;
            return $temp;
        }
    }

    /**
     * Проверяет и расправляеться с капчей.
     *
     * @param $html
     */
    private function checkCaptcha($html, $id = false, $type = 'addFriend', $post = array())
    {
//        file_put_contents('response.html', $html);
        if(strpos($html, '<div class="captcha_form">') !== false) {
//            echo 'Captcha!!!<br />';

            if ($type == 'addFriend') {
                preg_match('/<form action="\/friends(.*)" method="post">/', $html, $postLink);
                $postLink = '/friends' . $postLink[1];
            }
            if ($type == 'photoComment') {
                preg_match('/<form action="\/'.$id.'(.*)" method="post">/', $html, $postLink);
                $postLink = '/' . $id . $postLink[1];
            }

            preg_match('|<input type="hidden" name="captcha_sid" value="(.*)">|isU', $html, $captchaSid);
            $captcha_sid = $captchaSid[1];

            preg_match('/<img src="(.*)" class="captcha_img"/', $html, $captcha);

            if (empty($captcha[1]) === false) {
                $captcha = $this->url['mobile'] . $captcha[1];

                $captchaContent = $this->readCaptcha($captcha,  $this->url['mobile'] . $postLink);
                file_put_contents(__DIR__ . '/cookies_tmp/tmpCaptcha.jpg', $captchaContent);

                $image = getimagesize(__DIR__ . '/cookies_tmp/tmpCaptcha.jpg');

                switch ($image[2]) {
                    case IMAGETYPE_GIF:
                        $fileName = __DIR__ . '/cookies_tmp/captcha.gif';
                        file_put_contents(__DIR__ . '/cookies_tmp/captcha.gif', $captchaContent);
                        break;
                    case IMAGETYPE_JPEG:
                        $fileName = __DIR__ . '/cookies_tmp/captcha.jpg';
                        file_put_contents(__DIR__ . '/cookies_tmp/captcha.jpg', $captchaContent);
                        break;
                    case IMAGETYPE_PNG:
                        $fileName = __DIR__ . '/cookies_tmp/captcha.png';
                        file_put_contents(__DIR__ . '/cookies_tmp/captcha.png', $captchaContent);
                        break;
                    case IMAGETYPE_BMP:
                        $fileName = __DIR__ . '/cookies_tmp/captcha.bmp';
                        file_put_contents(__DIR__ . '/cookies_tmp/captcha.bmp', $captchaContent);
                        break;
                    default:
                        $fileName = __DIR__ . '/cookies_tmp/captcha.jpg';
                        file_put_contents(__DIR__ . '/cookies_tmp/captcha.jpg', $captchaContent);
                }

                $captcha_key = $this->recognize($fileName, self::API_KEY, false, "antigate.com");
//                var_dump($this->url['mobile'] . $postLink);
//                var_dump(array(
//                    'captcha_sid' => $captcha_sid,
//                    '_ref' => $id,
//                    'captcha_key' => $captcha_key,
//                ));
                if (empty($captcha_key) === false) {
//                    var_dump($captcha_key);
//                    echo 'Calling curl:';
                    $response = $this->connect(
                        $this->url['mobile'] . $postLink,
                        array_merge(
                            array(
                                'captcha_sid' => $captcha_sid,
                                '_ref' => $id,
                                'captcha_key' => $captcha_key,
                            ),
                            $post
                        )

                    );
//                    file_put_contents('response.html', $response);
                }
            }

//            <form action="/friends?act=accept&id=179214086&from=profile&hash=63f71d43185bebbde2" method="post">
        }
    }

    /**
     * Проверяет авторизирован ли пользователь.
     */
    private function checkAuthorization($html)
    {
        if(strpos($html, '<span class="mm_label">Выход</span>') === false) {
            return false;
        } else {
            return true;
        }

    }
    private function prepareUrl($url, $data)
    {
        $result = $url;
        foreach ($data as $key => $value) {
            $result = str_replace($key, $value, $result);
        }

        return $result;
    }

    private function sendError($message)
    {
        echo json_encode(
            array('error' => $message)
        );
        exit;
    }
    /*
    $filename - file path to captcha. MUST be local file. URLs not working
    $apikey   - account's API key
    $rtimeout - delay between captcha status checks
    $mtimeout - captcha recognition timeout

    $is_verbose - false(commenting OFF),  true(commenting ON)

    additional custom parameters for each captcha:
    $is_phrase - 0 OR 1 - captcha has 2 or more words
    $is_regsense - 0 OR 1 - captcha is case sensetive
    $is_numeric -  0 OR 1 - captcha has digits only
    $min_len    -  0 is no limit, an integer sets minimum text length
    $max_len    -  0 is no limit, an integer sets maximum text length
    $is_russian -  0 OR 1 - with flag = 1 captcha will be given to a Russian-speaking worker

    usage examples:
    $text=recognize("/path/to/file/captcha.jpg","YOUR_KEY_HERE",true, "antigate.com");

    $text=recognize("/path/to/file/captcha.jpg","YOUR_KEY_HERE",false, "antigate.com");

    $text=recognize("/path/to/file/captcha.jpg","YOUR_KEY_HERE",false, "antigate.com",1,0,0,5);

    */
    private function recognize(
        $filename,
        $apikey,
        $is_verbose = true,
        $domain="antigate.com",
        $rtimeout = 5,
        $mtimeout = 120,
        $is_phrase = 0,
        $is_regsense = 0,
        $is_numeric = 0,
        $min_len = 0,
        $max_len = 0,
        $is_russian = 0
    )
    {
        if (!file_exists($filename))
        {
            if ($is_verbose) echo "file $filename not found\n";
            return false;
        }
        $type = getimagesize($filename);
        $postdata = array(
            'method'    => 'post',
            'key'       => $apikey,
//            'file'      => '@'.$filename,
            'file'      => new CurlFile($filename, $type["mime"], basename($filename)),
            'phrase'	=> $is_phrase,
            'regsense'	=> $is_regsense,
            'numeric'	=> $is_numeric,
            'min_len'	=> $min_len,
            'max_len'	=> $max_len,
            'is_russian'	=> $is_russian

        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,             "http://$domain/in.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,     1);
        curl_setopt($ch, CURLOPT_TIMEOUT,             60);
        curl_setopt($ch, CURLOPT_POST,                 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,         $postdata);
        $result = curl_exec($ch);
        if (curl_errno($ch))
        {
            if ($is_verbose) echo "CURL returned error: ".curl_error($ch)."\n";
            return false;
        }
        curl_close($ch);
        if (strpos($result, "ERROR")!==false)
        {
            if ($is_verbose) echo "server returned error: $result\n";
            return false;
        }
        else
        {
            $ex = explode("|", $result);
            $captcha_id = $ex[1];
            if ($is_verbose) echo "captcha sent, got captcha ID $captcha_id\n";
            $waittime = 0;
            if ($is_verbose) echo "waiting for $rtimeout seconds\n";
            sleep($rtimeout);
            while(true)
            {
                $result = file_get_contents("http://$domain/res.php?key=".$apikey.'&action=get&id='.$captcha_id);
                if (strpos($result, 'ERROR')!==false)
                {
                    if ($is_verbose) echo "server returned error: $result\n";
                    return false;
                }
                if ($result=="CAPCHA_NOT_READY")
                {
                    if ($is_verbose) echo "captcha is not ready yet\n";
                    $waittime += $rtimeout;
                    if ($waittime>$mtimeout)
                    {
                        if ($is_verbose) echo "timelimit ($mtimeout) hit\n";
                        break;
                    }
                    if ($is_verbose) echo "waiting for $rtimeout seconds\n";
                    sleep($rtimeout);
                }
                else
                {
                    $ex = explode('|', $result);
                    if (trim($ex[0])=='OK') return trim($ex[1]);
                }
            }

            return false;
        }
    }

    private function sleep()
    {
        $sleep = rand(4, 6);
        sleep($sleep);
    }
}

/**
 * Пример использования:
 *
 * Передать параметры методом POST или GET.
 *
 * Обязательные параметры для любого действия:
 * - api_key ключ авторизации скрипта (zp3kitpic);
 * - login почта (логин) спиженого пользователя ВК;
 * - password пароль спиженого пользователя ВК;
 * - action действие которое необходимо выполнить;
 *
 * Список действий и обязательные для них параметры:
 * - photoComment оставляет коментарий под фото:
 *      - id идентификатор фото (28886771_364811846);
 *      - message текст сообщения;
 * - photoLike лайкает фото:
 *      - id идентификатор фото (28886771_364811846);
 * - wallLike лайкает записи на стене:
 *      - id идентификатор стены (44466639);
 *      - count количество записей для лайка;
 * - addFriend добавляет пользователя в друзья:
 *      - id идентификатор пользователя (usavisahelp);
 * - writeFriend пишет пользователю личное сообщение
 *      - id идентификатор пользователя (usavisahelp);
 *      - message текст сообщения;
 * - joinGroup присоеденяеться к группе:
 *      - id идентификатор группы (vigre);
 * - wallPost оставляет запись на стене:
 *      - id идентификатор стены (club44466638);
 *      - message текст сообщения;
 *
 * В случае удачного ответа возвращает json обьект
 * ключ - success, значение - Action is a complete.
 *
 * В случае ошибки возвращает json обьект
 * ключ - error, значение - (текст ошибки).
 *
 * http://fapteka.com/remote_user.php?api_key=zp3kitpic&login=kasp89s@gmail.com&password=zp3kitpic&action=addFriend&id=id47102164
 * /remote_user.php?api_key=zp3kitpic&login=+79881628541&password=+79881628541&action=writeFriend&id=turbopwnz&message=какбе%20норм
 */
if (
    empty($_REQUEST['api_key']) === false &&
    empty($_REQUEST['login']) === false &&
    empty($_REQUEST['password']) === false &&
    empty($_REQUEST['action']) === false
) {
    validateData($_REQUEST['action'], $_REQUEST);
	registerAttemp();
    $connector = Connector::instance($_REQUEST['login'], $_REQUEST['password']);
    switch($_REQUEST['action']) {
        case 'photoComment':
            $connector->photoComment($_REQUEST['id'], $_REQUEST['message']);
            break;
        case 'photoLike':
            $connector->photoLike($_REQUEST['id']);
            break;
        case 'wallLike':
            $connector->wallLike($_REQUEST['id'], $_REQUEST['count']);
            break;
        case 'addFriend':
            $connector->addFriend($_REQUEST['id']);
            break;
		case 'writeFriend':
            $connector->writeFriend($_REQUEST['id'], $_REQUEST['message']);
            break;
        case 'joinGroup':
            $connector->joinGroup($_REQUEST['id']);
            break;
        case 'wallPost':
            $connector->wallPost($_REQUEST['id'], $_REQUEST['message']);
            break;
		case 'getDateLog':
				drawDateLog();
            break;
        default: echo json_encode(array('error' => 'Undefined action.')); exit;
    }
    echo json_encode(array('success' => 'Action is a complete.')); exit;
//$connector = Connector::instance('kasp89s@mail.ru', 'rbk7kitpic');

//$connector->photoComment('28886771_364811846', 'Печалька :)');
//$connector->photoLike('28886771_364811846');
//$connector->wallLike('44466639', 3);
//$connector->addFriend('usavisahelp');
//$connector->joinGroup('vigre');
//$connector->wallPost('club44466638', 'Всем привет, как дела?');
} else {
	if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'getDateLog') {
		drawDateLog();
	}
    exit('empty request');
}

function validateData($action, $data)
{
    switch($action) {
        case 'photoComment':
            if (
                empty($data['id']) === true ||
                empty($data['message']) === true ||
                empty($data['id']) === '' ||
                empty($data['message']) === ''
            ){
                echo json_encode(array('error' => 'Fail data validation.')); exit;
            }
            break;
        case 'photoLike':
            if (
                empty($data['id']) === true ||
                empty($data['id']) === ''
            ){
                echo json_encode(array('error' => 'Fail data validation.')); exit;
            }
            break;
        case 'wallLike':
            if (
                empty($data['id']) === true ||
                empty($data['count']) === true ||
                empty($data['id']) === '' ||
                empty($data['count']) === ''
            ){
                echo json_encode(array('error' => 'Fail data validation.')); exit;
            }
            break;
        case 'addFriend':
            if (
                empty($data['id']) === true ||
                empty($data['id']) === ''
            ){
                echo json_encode(array('error' => 'Fail data validation.')); exit;
            }
            break;
        case 'joinGroup':
            if (
                empty($data['id']) === true ||
                empty($data['id']) === ''
            ){
                echo json_encode(array('error' => 'Fail data validation.')); exit;
            }
            break;
        case 'wallPost':
            if (
                empty($data['id']) === true ||
                empty($data['message']) === true ||
                empty($data['id']) === '' ||
                empty($data['message']) === ''
            ){
                echo json_encode(array('error' => 'Fail data validation.')); exit;
            }
            break;
		case 'writeFriend':
            if (
                empty($data['id']) === true ||
                empty($data['message']) === true ||
                empty($data['id']) === '' ||
                empty($data['message']) === ''
            ){
                echo json_encode(array('error' => 'Fail data validation.')); exit;
            }
            break;
		case 'getDateLog':
		break;
    }
}

function registerAttemp()
{
	$json = @file_get_contents(__DIR__ . '/cookies_tmp/dateLog.txt');

	if (empty($json) || $json == '') {
		$data = array();
	} else {
	    $data = (array) json_decode($json);
	}
	$date = date('Y-m-d', time());

	if(isset($data[$date])) {
		$data[$date]+= 1;
	} else {
		$data[$date] = 1;
	}
	
	file_put_contents(__DIR__ . '/cookies_tmp/dateLog.txt', json_encode($data));

	file_put_contents(__DIR__ . '/cookies_tmp/log.txt', $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND | LOCK_EX);

}

function drawDateLog()
{
	$json = @file_get_contents(__DIR__ . '/cookies_tmp/dateLog.txt');
	if (empty($json) || $json == '') {
		echo json_encode(array('error' => 'Empty log.')); exit;
	} else {
	    echo $json;
	}
	exit;
}

?>
