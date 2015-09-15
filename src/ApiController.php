<?php
/**
 * API контроллер Спортлига iLOT.
 *
 * @category   EMICT
 * @package    ILOT_SPORTLIGA
 * @subpackage CONTROLLERS
 * @link       /emict/sites/ilot/sportliga/controllers/ApiController.php
 */

namespace emict\sites\ilot\sportliga\controllers;

use \emict\main\components;
use \emict\main\models\sportliga;
use \emict\main\models\sportliga\Favorite;
use \emict\main\models\sportliga\esap;
use \emict\main\models\sportliga\mongo;

/**
 * API контроллер Спортлига iLOT.
 */
class ApiController extends BaseController
{
    /**
     * Количество записей единичной подгрузки результатов.
     */
    const RESULTS_LIMIT = 30;

    /**
     * Initialize.
     *
     * @param string $id
     * @param null $module
     */
    public function __construct($id, $module = null)
    {
        // патчим urlManager
        \Yii::app()->urlManager->setLanguage = false;
        parent::__construct($id, $module = null);
    }

    /**
     * Настройки прав доступа, используется схема: все, что не запрещено - разрешено.
     *
     * @return array
     */
    public function accessRules()
    {
        return [
            ['deny', 'actions' => ['favorites'], 'users' =>['?']],
        ];
    }

    /**
     * Колбек перед екшеном.
     *
     * @param \CAction $action
     * @return bool
     */
    public function beforeAction($action)
    {
        if (\Yii::app()->request->isPostRequest) {
            $post = file_get_contents("php://input");
            $data = json_decode($post, true);

            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    $_POST[$k] = $v;
                }
            }
        }

        return true;
    }

    /**
     * Фильтры перед выполнением действий.
     *
     * @return array
     */
    public function filters()
    {
        return [
            'ajaxOnly + eventChanges, getEvents, lastBets, favorites, sports, regions, tournaments, results, user',
            [
                '\emict\sites\ilot\sportliga\filters\ESAPAccess + user, favorites',
                'userModel' => 'player',
                'ajaxAnswerForActions' => [
                    'user'
                ]
            ],
            'accessControl'
        ];
    }

    /**
     * Обновления событий.
     *
     * @return void
     */
    public function actionEventChanges()
    {
        $timestamp = \Yii::app()->request->getQuery('t');

        // последний запрос > 5 часов назад
        if ($timestamp < time() - 60 * 60 * 5) {
            $timestamp = null;
        }

        if ($timestamp) {
            $deleteProcess = \Yii::app()->cache->get('deleteProcess');

            if (empty($deleteProcess) === true) {
                \Yii::app()->cache->set('deleteProcess', $timestamp);
                // удаляем начавшиеся события.
                $events = mongo\Event::model()->all(['date' => ['$lte' => new \MongoDate()]]);
                $changesDelete = [];

                foreach ($events as $event) {
                    $changesDelete[] = [
                        'event' => $event,
                        'type' => 'delete',
                        'date' => new \MongoDate()
                    ];
                    mongo\Event::model()->deleteByPk(new \MongoId($event['_id']));
                }

                // для скорости вставляем plain object
                if ($changesDelete) {
                    mongo\EventChange::model()->getCollection()->batchInsert($changesDelete);
                }
                \Yii::app()->cache->delete('deleteProcess');
            }

            $changes = mongo\EventChange::model()->all(['date' => ['$gte' => new \MongoDate($timestamp)]]);
            $this->sendJSON(200, $changes);
        }
    }

    /**
     * Возвращает json событий монги.
     *
     * @return string
     */
    public function actionGetEvents()
    {
        ini_set('memory_limit', '512M');
        $count = mongo\Event::model()->count();
        if ($count != \Yii::app()->cache->get('actualEventsCount')) {
            \Yii::app()->cache->set('actualEventsCount', $count);
            \Yii::app()->cache->delete('actualEvents');
        }

        $events = \Yii::app()->cache->get('actualEvents');
        if (empty($events) === true) {
            $events = json_encode(mongo\Event::model()->all(), JSON_UNESCAPED_UNICODE);
            \Yii::app()->cache->set('actualEvents', $events);
        }

        header('HTTP/1.1 200 OK');
        header('Content-type: application/json');
        echo $events;
        \Yii::app()->end();
    }

    /**
     * Последние ставки.
     *
     * @return void
     */
    public function actionLastBets()
    {
        $lastBets = mongo\LastBet::model()->all([], ['limit' => 6, 'sort' => ['date' => -1]]);
        $this->sendJSON(200, $lastBets);
    }

    /**
     * Добавить/удалить избранное.
     *
     * @return void
     */
    public function actionFavorites()
    {
        // говнорест
        if (\Yii::app()->request->isPostRequest) {
            $favorite = new Favorite();
            $favorite->type = \Yii::app()->request->getPost('type');
            $favorite->favoriteId = \Yii::app()->request->getPost('favoriteId');
            $favorite->playerId = $this->player->id;

            if ($favorite->save()) {
                $this->sendJSON(200, $favorite->attributes);
            } else {
                $this->sendJSON(400, $favorite->errors);
            }
        }

        if (\Yii::app()->request->isDeleteRequest) {
            $id = \Yii::app()->request->getQuery('id');

            if ($id && Favorite::model()->deleteByPk($id, 'playerId = :playerId', ['playerId' => $this->player->id])) {
                $this->sendJSON(200);
            } else {
                $this->sendJSON(400);
            }
        }
    }

    /**
     * Sports.
     *
     * @return void
     */
    public function actionSports()
    {
        $sports = sportliga\Sport::model()->findAll();
        $this->makeJSON($sports);
    }

    /**
     * Regions.
     *
     * @return void
     */
    public function actionRegions()
    {
        $key = 'sl-regions';

        $regionsArray = \Yii::app()->cache->get($key);
        if ($regionsArray === false) {
            $regions = sportliga\Region::model()->findAll();
            $regionsArray = $this->makeJSON($regions, true);
            \Yii::app()->cache->set($key, $regionsArray, 60);
        }

        $this->sendJSON(200, $regionsArray);
    }

    /**
     * Tournaments.
     *
     * @return void
     */
    public function actionTournaments()
    {
        $sportId = \Yii::app()->request->getQuery('sportId');
        $regionId = \Yii::app()->request->getQuery('regionId');
        $key = 'sl-tournaments-sport' . $sportId . '-region' . $regionId;

        $tournamentsArray = \Yii::app()->cache->get($key);
        if ($tournamentsArray === false) {
            $criteria = new \CDbCriteria();
            if (\Yii::app()->request->getQuery('sportId')) {
                $criteria->compare('sportId', $sportId);
            }

            if (\Yii::app()->request->getQuery('regionId')) {
                $criteria->compare('regionId', $regionId);
            }
            $tournaments = sportliga\Tournament::model()->findAll($criteria);
            $tournamentsArray = $this->makeJSON($tournaments, true);
            \Yii::app()->cache->set($key, $tournamentsArray, 60);
        }

        $this->sendJSON(200, $tournamentsArray);
    }

    /**
     * Results.
     *
     * @return void
     */
    public function actionResults()
    {
        $condition = [];
        $condition['tournament.tournamentId'] = \Yii::app()->request->getQuery('tournamentId');
        $condition['sport.sportId'] = \Yii::app()->request->getQuery('sportId');
        $condition['region.regionId'] = \Yii::app()->request->getQuery('regionId');
        $condition = array_filter($condition);
        $condition = array_map('intval', $condition);

        $offset = \Yii::app()->request->getQuery('offset', 0);
        $count = \Yii::app()->request->getQuery('count', 0);

        $periodAttr = \Yii::app()->request->getQuery('period');
        $gte = strtotime($periodAttr);

        if (empty($gte) === true) {
            $gte = strtotime(date('Y-m-d 00:00:00', time()));
        }

        $lte = $gte + 86400;
        $search = \Yii::app()->request->getQuery('search');

        if ($search && mb_strlen($search) >= 3) {
            foreach (\Yii::app()->params['languages'] as $lang) {
                $condition['$or'][] = ['homeCompetitors.name.' . $lang => new \MongoRegex('/' . $search . '/i')];
                $condition['$or'][] = ['awayCompetitors.name.' . $lang => new \MongoRegex('/' . $search . '/i')];
            }
        }

        $condition = array_merge(['date' => ['$gte' => new \MongoDate($gte), '$lte' => new \MongoDate($lte)]], $condition);

        $results = ['noMoreEvents' => false, 'data' => []];

        if (empty($count) === true || (int) $count == 0) {
            $count = mongo\Result::model()->countByAttributes($condition);
        }

        if ($count <= $offset + self::RESULTS_LIMIT) {
            $results['noMoreEvents'] = true;

            if ((int) $offset > 0) {
                $this->sendJSON(200, $results);
            }
        }

        $results['data'] = mongo\Result::model()->all($condition, ['limit' => self::RESULTS_LIMIT, 'offset' => $offset]);
        $this->sendJSON(200, $results);
    }

    /**
     * Current user.
     *
     * @return void
     */
    public function actionUser()
    {
        $this->sendJSON(200, $this->player->toArray());
    }

    /**
     * Build and send json.
     *
     * @param \CActiveRecord[] records
     * @param bool $return
     *
     * @return null|array
     */
    protected function makeJSON(array $data, $return = false)
    {
        $array = [];
        foreach ($data as $item) {
            $itemArray = $item->attributes;
            $names = $this->getNames($item);
            if ($names) {
                $itemArray['name'] = $names;
            }

            $array[] = $itemArray;
        }

        if (!$return) {
            $this->sendJSON(200, $array);
        }
        return $array;
    }

    /**
     * Get names from model.
     *
     * @param sportliga\Translatable $item
     * @return array
     */
    protected function getNames(sportliga\Translatable $item)
    {
        $names = [];
        foreach (\Yii::app()->params['languages'] as $lang) {
            if (isset($item->{$lang}) && isset($item->{$lang}->name)) {
                $names[$lang] = $item->{$lang}->name;
            }
        }
        return $names;
    }
}
