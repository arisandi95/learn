<?php

namespace kanwildiy\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use kanwildiy\models\Konten;
use kanwildiy\models\Uploads;
use kanwildiy\models\LoginForm;
use yii\db\Query;

class FrontController extends \docotel\dcms\components\BaseController
{
    private $userService;

    public function __construct($id, $module, $config = [])
    {
       Yii::$container->setSingleton('docotel\dcms\components\bll\IUserService',
            'docotel\dcms\components\bll\UserService');
        $this->userService = Yii::$container->get('docotel\dcms\components\bll\IUserService');

        parent::__construct($id, $module, $config);
    }

    public function beforeAction($event)
    {
        //set themes
        Yii::$app->view->theme->pathMap = ['@app/views' => '@webroot/themes/front'];
        Yii::$app->view->theme->baseUrl = '@kanwildiy/web/themes/front';
        //set layout
        $this->layout = 'main';
        return parent::beforeAction($event);
    }

    public function actionIndex()
    {
        if (!Yii::$app->user->isGuest) {
            $userId = Yii::$app->session->get('user_id');
            $url = $this->userService->getRedirectUrl($userId);
            if (!empty(reset($url))) {
                return $this->redirect([reset($url)]);
            }
            $this->redirect('dashboard');
        }
        $this->layout = 'main_depan';
        $session = Yii::$app->session;
        $session->remove('loginAs');
        return $this->render('depan');
    }

    public function actionView($jenis) {
        $model = $judul = NULL;
        if(!empty($jenis) && array_key_exists($jenis, Konten::$tipe)) {
            $model = Konten::find()->where(['tipe_konten' => $jenis, 'is_publish' => 1])->all();
            $judul = Konten::$tipe[$jenis];

            switch ($jenis) {
                case '1':
                case '2':
                    $view = "view";
                    break;
                case '3':
                    $view = "pengumuman";
                    break;
                case '4':
                    $view = "tentang";
                    break;
            }

            return $this->render( $view,
                    [
                        'model' => $model,
                        'judul' => $judul,
                    ]
            );
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionDownload($id)
    {
        if(!empty($id)) {
            if ( ($uploads = Uploads::findOne($id)) !== null ) {
                $path = Yii::getAlias('@app') . '/web/uploads/konten';
                $file = $path . '/' . $uploads->file;
                if ( file_exists($file) ) {
                    if ( !is_file($file) ) {
                        throw new NotFoundHttpException('The file does not exists.');
                    }
                    return Yii::$app->response->sendFile($file);
                } else {
                    throw new NotFoundHttpException('The file does not exists.');
                }
            } else {
                throw new NotFoundHttpException('The file does not exists.');
            }
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionDashboard()
    {
        return $this->render('dashboard');
    }

}
