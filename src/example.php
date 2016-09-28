<?php

namespace emict\main\controllers\msl;

use emict\main\components;
use emict\main\models\esap\Player;
use emict\main\models\Page;
use emict\main\models\msl\InterestingFact;
use emict\main\models\Ticket;
use emict\main\models\SeoPage;

/**
 * Базовый контроллер для лотерей iLOT.
 */
abstract class LotteryController extends \CController
{
    /**
     * Код лотереи. Определяет к какой лотерее относится контроллер.
     */
    const LOTTERY_CODE = 'msl/index';

    /**
     * Возникает в методе sendAjaxResponse в случае не правильных данных.
     */
    const ERROR_WRONG_AJAX_DATA = 1;

    /**
     * Лейаут по умолчанию, в отличии от оффициальной документации.
     *
     * @var string
     */
    public $layout = '//layouts/default';

    /**
     * Модель игрока, инициализируется в контроллере.
     *
     * @var \emict\main\models\esap\Player
     */
    public $player;

    /**
     * Сео.
     *
     * @var
     */
    public $seo;

    /**
     * Список AJAX ответов.
     *
     * @var array
     */
    public static $ajaxResponses = [
        'wrongData' => ['status' => 'error', 'action' => 'error'],
        'success' => ['status' => 'success'],
        'internalError' => ['status' => 'error', 'action' => 'internal']
    ];
    public $lotteryName;

    public static $LotteryNames = [
        'default' => 'Главная',
        'lotozabava' => 'Забава',
        'gonkaNaGroshi' => 'Гонка на гроши',
        'ktotam' => 'Хто там',
        'megalot' => 'Мегалот',
        'shvydkogray' => 'Моменталки',
        'sportliga' => 'Спортлига',
        'sportprognoz' => 'Спортпрогноз',
        'tiptop' => 'Тип Топ',
    ];

    public function init()
    {
        $this->player = new Player;
        $current = \Yii::app()->controller->id;
        $this->lotteryName = (isset(self::$LotteryNames[$current])) ? self::$LotteryNames[$current] : null;

        $this->seo = SeoPage::getByUri($_SERVER['REQUEST_URI']);
   }
    /**
     * Фильтры перед выполнением действий.
     *
     * @return array
     */
    public function filters()
    {
        return [
            ['\emict\main\filters\ReferralFilter'],
            ['\emict\main\filters\LanguageRedirect']
        ];
    }

    /**
     * Главная страница.
     *
     * Адрес: http://msl.ua/{LOTTERY_NAME}/ru/.
     *
     * @throws \CException Исключение сообщает о необходимости переопределить метод.
     * @return void
     */
    public function actionIndex()
    {
        throw new \CException('Not implemented method');
    }

    /**
     * Страница перехвата ошибок.
     *
     * Используется например для 404 ошибки.
     *
     * Адрес: http://msl.ua/{LOTTERY_NAME}/ru/error.
     *
     * @throws \CException Исключение сообщает о необходимости переопределить метод.
     * @return void
     */
    public function actionError()
    {
        $this->layout = 'static';
        $this->render('404');
    }

    /**
     * Заменяет стнадартный render для проброса параметров в layout.
     *
     * CComponent по умолчанию пробрасывает данные только в шаблон.
     *
     * @param string $view   Название, или относительный путь к шаблону.
     * @param null|array $data   Данные, которые передаются и в layout и в шаблон.
     * @param boolean $return Выводить, или возвращать отрендеренные данне.
     *
     * @return string|null
     */
    public function render($view, $data = null, $return = false)
    {
        if ($this->beforeRender($view)) {
            $output = $this->renderPartial($view, $data, true);
            $layoutFile = $this->getLayoutFile($this->layout);
            if ($layoutFile !== false) {
                $layoutData = ['content' => $output];
                if (is_array($data) === true) {
                    $layoutData = \CMap::mergeArray($layoutData, $data);
                }

                $output = $this->renderFile($layoutFile, $layoutData, true);
            }

            $this->afterRender($view, $output);

            $output = $this->processOutput($output);

            if ($return) {
                return $output;
            } else {
                echo $output;
            }
        }
    }

    /**
     * Выполняет отправку JSON ответа на базе self::$ajaxResponses.
     *
     * @param string $name       Название исходника ответа.
     * @param array $additional Дополнительные данные ответа.
     *
     * @return void
     */
    public static function sendAjaxResponse($name, $additional = [])
    {
        header('Content-type: application/json');
        $data = \CMap::mergeArray(self::$ajaxResponses[$name], $additional);
        echo \CJSON::encode($data);
        \Yii::app()->end();
    }

    /**
     * Проверка билета на выигрыш.
     *
     * Страница доступна только для AJAX запросов.
     * Адрес: http://msl.ua/{$lotteryCode}/ru/ticketWinCheck.
     *
     * @return void
     */
    public function actionTicketWinCheck()
    {
        if (\Yii::app()->request->isPostRequest === false) {
            return;
        }

        $post = $_POST['emict_main_models_megalot_esap_Ticket'];

        $wrongResponse = ['errors' => Ticket::getMessages('wrongNumber')];
        $lotteryCodeId = substr($post['macCode'], 0, 3);
        $lotteryMacCodeParams = \Yii::app()->params['lotteryMacCode'];

        if (empty($lotteryMacCodeParams[$lotteryCodeId]) === true) {
            self::sendAjaxResponse('wrongData', $wrongResponse);
        }

        $lotteryCode = $lotteryMacCodeParams[$lotteryCodeId];
        $esapTicket = Ticket::eSAPfactory($lotteryCode);
        $response = $esapTicket->ticketWinCheck(['macCode' => $post['macCode']]);

        if (!empty($response) && $response['err_code'] !== '0') {
            $wrongResponse = ['errors' => $response['err_descr']];
            self::sendAjaxResponse('wrongData', $wrongResponse);
        } elseif (empty($response)) {
            self::sendAjaxResponse('wrongData', ['errors' => Ticket::getMessages('wrongNumber')]);
        }

        if ($esapTicket->winAmount > 0) {
            $params = ['win' => true, 'sum' => $esapTicket->formatWinAmount()];
        } else {
            $params = ['win' => false];
        }

        self::sendAjaxResponse('success', $params);
    }

    /**
     * Статические страницы.
     *
     * Адрес: http://msl.ua/{$lotteryCode}/ru/static
     *
     * @param string         $category Категория статической страницы.
     * @param boolean|string $alias    Псевдоним страницы.
     *
     * @return void
     */
    public function actionStatic($category = 'static', $alias = false)
    {
        $this->layout = 'static';
        if ($alias === false) {
            $this->show404();
        }

        $lotteryCode = static::LOTTERY_CODE;

        $page = Page::model()->with('icon')
            ->find('alias = :alias AND category = :category AND lotteryCode = :lotteryCode',
            [
                ':alias' => $alias,
                ':category' => $category,
                ':lotteryCode' => $lotteryCode
            ]
        );
        if ($page === null) {
            $this->show404();
        }
        $language = \Yii::app()->language;
        $this->pageTitle = $page->{$language}->title;
        $icon = ($page->iconStorageId === null) ? '' : '/storage' . $page->icon->path;

        if (empty($this->seo)) {
            $this->seo = (object) [
                'ogImage' => $page->ogImage,
                'ogTitle' => $page->{$language}->ogTitle,
                'ogDescription' => $page->{$language}->ogDescription,
            ];
        } else {
            if ($page->ogImage != '') {
                $this->seo->ogImage = $page->ogImage;
            }
            if ($page->{$language}->ogTitle != '') {
                $this->seo->ogTitle = $page->{$language}->ogTitle;
            }
            if ($page->{$language}->ogDescription != '') {
                $this->seo->ogDescription = $page->{$language}->ogDescription;
            }
        }
        $this->render('static', ['page' => $page->{$language}->content, 'icon' => $icon]);
    }

    /**
     * Ошибка Страница не найдена.
     *
     * @throws \CHttpException
     *
     * @return void
     */
    public function show404()
    {
        throw new \CHttpException(404, \Yii::t('msl/index', 'Страница не найдена'));
    }

    /**
     * Достает случайный факт для страниц лотерей МСЛ.
     *
     * @param string $lotteryCode Код лотереи.
     *
     * @return array
     * @since {VERSION}
     */
    public function getRandomFact($lotteryCode)
    {
        $fact = InterestingFact::model()->getRandomfact($lotteryCode);

        $renderedFact = false;
        if (isset($fact) === true) {
            $renderedFact = $this->renderPartial('block/fact', ['fact' => $fact], true);
        }
        return $renderedFact;
    }

    /**
     * Возвращает значение POST переменной или значение по умолчанию.
     *
     * @param string $name      Название переменной.
     * @param mixed $default    Значение переменной по умолчанию.
     *
     * @return mixed
     */
    protected function getPostValue($name, $default = null)
    {
        if (isset($_POST[$name]) === true) {
            $value = $_POST[$name];
        } else {
            $value = $default;
        }
        return $value;
    }
}
