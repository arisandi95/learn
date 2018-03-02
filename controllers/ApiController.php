<?php

namespace kanwildiy\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\UploadedFile;
use yii\web\HttpException;
use kanwildiy\models\Setting;
use kanwildiy\components\Helper;
use kanwildiy\components\RestApi;
use kanwildiy\components\bll\BllUser;
use kanwildiy\components\dal\DalUser;
use kanwildiy\components\dal\DalUserDevice;
use kanwildiy\components\bll\BllNotification;

class ApiController extends Controller
{
    private $AUTH_KEY = "API-KANWIL-JOGJA";
    private $token = [
        "gettargetpnbp" => "jXr3x6dXV3v7qhh4swSZ2w=="
    ];
    private $deviceService;
    private $pengaduanService;

    public function __construct($id, $modules, $config = [])
    {
        Yii::$container->setSingleton('kanwildiy\components\dal\IDalUserDevice', 'kanwildiy\components\dal\DalUserDevice');
        $this->deviceService = Yii::$container->get('kanwildiy\components\dal\IDalUserDevice');

        Yii::$container->setSingleton('kanwildiy\components\bll\IBllPengaduan', 'kanwildiy\components\bll\BllPengaduan');
        $this->pengaduanService = Yii::$container->get('kanwildiy\components\bll\IBllPengaduan');

        parent::__construct($id, $modules, $config);
    }

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => \yii\filters\VerbFilter::className(),
                'actions' => [
                    'gettargetpnbp' => ['GET'],
                    'login' => ['POST'],
                    'pushToken' => ['POST'],
                    'notification' => ['GET'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        // var_dump(RestApi::encrypt($action->id, $this->AUTH_KEY), \Yii::$app->request->get('TOKEN'));exit;
        if(RestApi::encrypt($action->id, $this->AUTH_KEY) === \Yii::$app->request->get('TOKEN')) {
            return parent::beforeAction($action);
        }
        throw new HttpException(400);
    }

    public function actionGettargetpnbp() {
        $val = Setting::findOne(['key' => 'target_pnbp']);
        if($val) {
            $val = $val->value;
        }
        return Helper::jsonParse($val);
    }

    public function actionLogin()
    {
        $username = \Yii::$app->request->post('username');
        $password = \Yii::$app->request->post('password');
        $user = DalUser::getUserByUsername($username);
        $dePass = BllUser::DecryptPass($password);
        $validatePass = !empty($user) ? $user->validatePassword($dePass) : false;
        $validasi = BllUser::validasiServiceLogin($user, $dePass, $validatePass);
        return RestApi::response($validasi['status'], $validasi['data']);
    }

    public function actionLogout()
    {
        $id = \Yii::$app->request->post('user_id');
        $flag = $this->deviceService->updateFlagLoginDevice($id, 'logout');
        return RestApi::response($flag ? 200 : 500, $flag);
    }

    public function actionPushToken()
    {
        $id = \Yii::$app->request->post('user_id');
        $token = \Yii::$app->request->post('token');
        $data = $this->deviceService->getTokenById($id);
        if (empty($data)) {
            $val = ['user_id' => $id, 'token' => $token];
            $data = $this->deviceService->insertDevice($val);
        } else {
            $data = $this->deviceService->updateToken($id, $token);
        }
        if ($data === true) {
            return RestApi::response(200, $data);
        } else {
            return RestApi::response(500, $data);
        }
    }

    public function actionChangePassword()
    {
        $id = \Yii::$app->request->post('user_id');
        $old = \Yii::$app->request->post('old_password');
        $new = \Yii::$app->request->post('new_password');
        $deOld = RestApi::decrypt($old, RestApi::$aes_key);
        $deNew = RestApi::decrypt($new, RestApi::$aes_key);
        $validasi = BllUser::validasiChangePassword($id, $deOld, $deNew);
        return RestApi::response($validasi['status'], $validasi['data']);
    }

    public function actionUpdateProfile()
    {
        $post = [
            'id' => \Yii::$app->request->post('user_id'),
            'fullname' => \Yii::$app->request->post('fullname'),
            'email' => \Yii::$app->request->post('email'),
            'telp' => \Yii::$app->request->post('telp'),
            'photo' => UploadedFile::getInstanceByName('photo'),
            'alamat' => \Yii::$app->request->post('alamat'),
        ];
        if (Yii::$app->request->isPost) {
            $validasi = BllUser::validasiServiceUpdateProfile($post);
        }
        return RestApi::response($validasi['status'], $validasi['data']);
    }

    public function actionNotification()
    {
        $id = \Yii::$app->request->get('id_user');
        $offset = \Yii::$app->request->get('offset');
        $limit = \Yii::$app->request->get('limit');
        $notification = BllNotification::getAllNotification($id, $offset, $limit);
        return RestApi::response($notification['status'], $notification['data']);
    }

    public function actionReadNotification()
    {
        $id = \Yii::$app->request->post('id_notification');
        $read = BllNotification::readNotification($id);
        return RestApi::response($read['status'], $read['data']);
    }

    public function actionStatistikPengaduan()
    {
        $id = \Yii::$app->request->post('id_pengaduan');
        $tahun = \Yii::$app->request->post('tahun');
        $pengaduan = $this->pengaduanService->getStatistik($id, $tahun);
        return RestApi::response($pengaduan['status'], $pengaduan['data']);
    }

}