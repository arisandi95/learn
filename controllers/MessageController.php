<?php

namespace kanwildiy\controllers;

use Yii;
use kanwildiy\models\Message;
use kanwildiy\components\Helper;
use kanwildiy\models\MessageSearch;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use docotel\dcms\components\BaseController;

/**
 * MessageController implements the CRUD actions for Message model.
 */
class MessageController extends BaseController
{
    private $messageService;

    public function __construct($id, $modules, $config = [])
    {
        Yii::$container->setSingleton('kanwildiy\components\bll\IMessageService',
            'kanwildiy\components\bll\MessageService');
        $this->messageService = Yii::$container->get('kanwildiy\components\bll\IMessageService');

        parent::__construct($id, $modules, $config);
    }

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Message models.
     * @return mixed
     */
    public function actionIndex()
    {
        $user = Helper::def(Yii::$app->user->identity, 'username');
        $searchModel = new MessageSearch();
        $message_user = Message::find()->joinWith('messageIn t')->andWhere(['t.is_deleted' => '0'])->andWhere(['t.receiver' => $user])->all();

        if (!empty($message_user)) {
            $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
            $dataProvider->query->joinWith('messageIn t');
            $dataProvider->query->andWhere(['t.is_deleted' => '0']);
            $dataProvider->query->andWhere(['t.receiver' => $user]);
            $dataProvider->query->orderby(['message.created_at' => SORT_DESC]);
        } else {
            $all_group = Yii::$app->user->identity->userGroup;
            $group = '';
            if ($all_group) {
                $count = count($all_group);
                foreach ($all_group as $key => $value) {
                    if ($count == $key+1) {
                        $group .= $value->group_id;
                    } else {
                        $group .= $value->group_id.', ';
                    }
                }
            }
            $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
            $dataProvider->query->joinWith('messageIn t');
            $dataProvider->query->andWhere(['t.is_deleted' => '0']);
            $dataProvider->query->andWhere('t.receiver in ("'.$group.'")');
            $dataProvider->query->orderby(['message.created_at' => SORT_DESC]);
        }

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    public function actionView($id)
    {
        $user = Helper::def(Yii::$app->user->identity, 'username');
        if ($id) {
            $model_user = $this->messageService->cekMessageByUser($id);
            $model_group = $this->messageService->cekMessageByGroup($id);

            if (!empty($model_user) || !empty($model_group)) {
                if (!empty($model_user)) {
                    $this->messageService->readMessage($id, $user);
                } else {
                    $this->messageService->readMessageGroup($id);
                }
                return $this->render('view', [
                    'model' => $this->findModel($id),
                ]);
            }
        }
        throw new NotFoundHttpException('The requested page does not exist.');

    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->message_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    protected function findModel($id)
    {
        if (($model = Message::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
