<?php
namespace kanwildiy\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use kanwildiy\models\LoginForm;
use kanwildiy\models\PasswordResetRequestForm;
use kanwildiy\models\ResetPasswordForm;
use kanwildiy\models\SignupForm;
use kanwildiy\models\ContactForm;
use docotel\dcms\behaviors\LoggableBehavior;
use kanwildiy\components\RestApi;
use kanwildiy\models\Setting;
use kanwildiy\components\dal\DalBilling;
use kanwildiy\components\Helper;
use kanwildiy\models\Konten;
use kanwildiy\models\KontenSearch;
use kanwildiy\components\dal\DalBuku;
use kanwildiy\components\dal\DalNotaris;
use kanwildiy\components\bll\BllNotaris;
use kanwildiy\models\Agama;
use kanwildiy\models\Uploads;
use yii\web\NotFoundHttpException;
use kanwildiy\components\dal\DalKonten;
use yii\helpers\Url;
use kanwildiy\models\Notaris;
use kanwildiy\components\dal\DalWilayah;
use kanwildiy\components\dal\DalUser;
use yii\web\Response;
/**
 * Site controller
 */
class SiteController extends \docotel\dcms\components\BaseController
{
    private $userService;

    public function __construct($id, $module, $config = [])
    {
       Yii::$container->setSingleton('docotel\dcms\components\bll\IUserService',
            'docotel\dcms\components\bll\UserService');
        $this->userService = Yii::$container->get('docotel\dcms\components\bll\IUserService');

        parent::__construct($id, $module, $config);
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
                'view' => '@app/views/site/error.php'
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $this->redirect('dashboard');
    }

    public function actionDashboard()
    {
        $group = Yii::$app->session->get('group_id');
        if (reset($group) == 'notaris') {
            $this->redirect('notaris');
        }
        set_time_limit(0);
        ini_set("memory_limit", "-1");
        $target = Setting::find()->where(['key' => 'target_pnbp'])->one();
        $target_currency = Yii::$app->formatter->format($target->value, 'currency');
        $target = !empty($target->value) ? $target_currency : '0';
        $year = date('Y');
        $laporan = DalBuku::notarisLapor($year, false);
        $taklaporan = DalBuku::notarisLapor($year, true);
        $grafik_merah = DalBuku::hitungBulanan(1, $year);
        $grafik_biru  = DalBuku::hitungBulanan(2, $year);
        $grafik_hijau = DalBuku::hitungBulanan(3, $year);
        $grafik_nihil = DalBuku::hitungBulanan(5, $year);
        $pnbp = DalBilling::IncomeThisYear();
        $pnbp = Yii::$app->formatter->format($pnbp, 'currency');
        $pnbp = !empty($pnbp) ? $pnbp : '0';
        return $this->render('admin_index',
            [
                'target' => $target,
                'grafik_merah' => $grafik_merah,
                'grafik_biru' => $grafik_biru,
                'grafik_hijau' => $grafik_hijau,
                'grafik_nihil' => $grafik_nihil,
                'pnbp' => $pnbp,
                'laporan' => $laporan,
                'taklaporan' => $taklaporan,
            ]);
    }

    /**
     * Logs in a user.
     *
     * @return mixed
     */
    public function actionLogin($tipe = null)
    {
    header('Cache-Control: no cache');
    session_cache_limiter('private_no_expire');
        if (!Yii::$app->user->isGuest) {
            $userId = Yii::$app->session->get('user_id');
            $url = $this->userService->getRedirectUrl($userId);
            if (!empty(reset($url))) {
                return $this->redirect([reset($url)]);
            }
            $this->redirect('dashboard');
        }
        $session = Yii::$app->session;
        $model = new LoginForm();
        $post = Yii::$app->request->post('LoginForm');
        if (!empty($post) && isset($post)) {
            $loginAs = $post['role'];
            if (!$session->has('loginAs')) {
                $loginAs = !empty($loginAs) ? $loginAs : 'notaris';
                Yii::$app->session->set('loginAs', $loginAs);
            }
            $group = Yii::$app->session->get('loginAs');
            if ($group == 'pengaduan-masyarakat') {
                $this->redirect('/pelaporan/pengaduan/formulir');
            } elseif ($group == 'pengaduan-polisi') {
                $this->redirect('/pelaporan/polisi/formulir');
            }
            $model->attributes = $post;
            if ($group == 'notaris') {
                $userId = DalUser::manageLogin($model, $post);
                if (!empty($userId)) {
                    $validateForm = $this->userService->login($model);
                    Yii::$app->session->set('id', $userId);
                    $url = $this->userService->getRedirectUrl($userId);
                    if (!empty(reset($url)) && $validateForm) {
                        return $this->redirect([reset($url)]);
                    } else {
                        Yii::$app->session->setFlash('alert', 'username atau password salah.');
                        Yii::$app->session->setFlash('alert-class', 'alert-warning');
                        $this->redirect('/');
                    }
                } else {
                    Yii::$app->session->setFlash('alert', 'username atau password notaris salah.');
                    Yii::$app->session->setFlash('alert-class', 'alert-warning');
                    $this->redirect('/');
                }
            } else {
                if ($model->validate()) {
                    $validateForm = $this->userService->login($model);
                    if($validateForm) {
                        $userId = Yii::$app->user->getId();
                        $url = $this->userService->getRedirectUrl($userId);

                        $query = new Query;
                        $rows = $query->select('id')
                            ->from('user')
                            ->where(['id' => $userId])
                            ->one(Yii::$app->db);
                        if(!empty($rows))
                            Yii::$app->session->set('id', $rows['id']);

                        // redirect to default url
                        if (!empty(reset($url))) {
                            if(empty($tipe))
                                return $this->redirect([reset($url)]);
                            else
                                return $this->redirect([$this->goUrl($tipe)]);
                        } else {
                            return $this->redirect('/site/login');
                        }
                    }
                } else {
                    BllNotaris::errorValidateFlash($model->errors);
                    $this->redirect('/');
                }
            }

        } else {
            $this->redirect('/');
        }

    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();
        unset($_SESSION);

        return $this->goHome();
    }

    /**
     * Signs user up.
     *
     * @return mixed
     */
    public function actionSignup()
    {
        $model = new SignupForm();
        $post = $model->load(Yii::$app->request->post());
        if ($post) {
            if ($model->signup()) {
                return $this->goHome();
            }
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    /**
     * Requests password reset.
     *
     * @return mixed
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        $post = $model->load(Yii::$app->request->post());
        $email = Yii::$app->request->post('PasswordResetRequestForm');
        if ($post && $model->validate() && $email) {
            $sendEmail = $model->sendEmail($email['email']);
            if ($sendEmail) {
                Yii::$app->session->setFlash('success', 'Check your email for further instructions.');

                return $this->goHome();
            } else {
                Yii::$app->session->setFlash('error', 'Sorry, we are unable to reset password for email provided.');
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * Resets password.
     *
     * @param string $token
     * @return mixed
     * @throws BadRequestHttpException
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
        $post = $model->load(Yii::$app->request->post());
        $resetPassword = $model->resetPassword();
        if ($post && $model->validate() && $resetPassword) {
            Yii::$app->session->setFlash('success', 'New password was saved.');

            return $this->goHome();
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }

    public function actionDetail()
    {
        return $this->render('detail');
    }

    public function actionKontak()
    {
        $phone = Setting::find()->where(['key' => 'telephone'])->one();
        $phone2 = Setting::find()->where(['key' => 'telephone_other'])->one();
        $address = Setting::find()->where(['key' => 'address'])->one();
        $web = Setting::find()->where(['key' => 'web'])->one();
        $email = Setting::find()->where(['key' => 'email'])->one();
        $latitude = Setting::find()->where(['key' => 'lat_address'])->one();
        $longitude = Setting::find()->where(['key' => 'lon_address'])->one();
        return $this->render('kontak', get_defined_vars());
    }

    public function actionSekilasNotaris()
    {
        $searchModel = new KontenSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $title = 'Sekilas Notaris';

        return $this->render('konten', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'title' => $title
        ]);
    }

    public function actionView($id)
    {
        if (!empty($id)) {
            $model = Konten::find()->where(['id_konten' => $id])->one();
            return $this->render('view', get_defined_vars());
        }
    }

    public function actionPengumuman()
    {
        $searchModel = new KontenSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $title = 'Pengumuman';
        return $this->render('konten', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'title' => $title
        ]);
    }

    public function actionPeraturan()
    {
        $searchModel = new KontenSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $title = 'Peraturan-peraturan';

        return $this->render('konten', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'title' => $title
        ]);
    }

    public function actionNotaris()
    {
        $username = Yii::$app->user->identity->username;
        $pengumuman = DalKonten::getPengumuman();
        $detail = Notaris::find()->where(['username' => $username])->one();
        if (!empty($detail)) {
            $detail->jenis_kelamin = ($detail->jenis_kelamin == 0) ? 'Laki-laki' : 'Perempuan';
            $detail->id_status_perkawinan = BllNotaris::getPerkawinan($detail->id_status_perkawinan);
            $detail->alamat_kecamatan_id = DalWilayah::getNameById($detail->alamat_kecamatan_id);
            $detail->alamat_kabupaten_id = DalWilayah::getNameById($detail->alamat_kabupaten_id);
            $detail->alamat_provinsi_id = DalWilayah::getNameById($detail->alamat_provinsi_id);
            $detail->pensiun = RestApi::getApi('NotarisPensiun',['tgl_lahir' => $detail->tanggal_lahir]);
            $detail->tanggal_lahir = Helper::formatDateIndonesia($detail->tanggal_lahir);
            if ($detail->tanggal_sertifikat_kodetik && $detail->tanggal_sertifikat_kodetik != '0000-00-00')
                $detail->tanggal_sertifikat_kodetik = Helper::formatDateIndonesia($detail->tanggal_sertifikat_kodetik);
            if ($detail->tanggal_sk_pelantikan && $detail->tanggal_sk_pelantikan != '0000-00-00')
                $detail->tanggal_sk_pelantikan = Helper::formatDateIndonesia($detail->tanggal_sk_pelantikan);
            $detail->id_agama = Agama::getAgama($detail->id_agama);
            $detail->nomor_telpon_json = Helper::getTelp($detail->nomor_telpon_json);
            $kantor = '';
            if (!empty($detail->ADDITIONAL)) {
                $kantor = json_decode($detail->ADDITIONAL);
                $kantor->provinsi_kantor = DalWilayah::getNameById($kantor->provinsi_kantor);
                $kantor->kabupaten_kantor = DalWilayah::getNameById($kantor->kabupaten_kantor);
                $kantor->kecamatan_kantor = DalWilayah::getNameById($kantor->kecamatan_kantor);
                if (!empty($kantor->tgl_akta_lahir)) {
                    $kantor->tgl_akta_lahir = Helper::formatDateIndonesia($kantor->tgl_akta_lahir);
                }
            }
            $summary = RestApi::getSummary($detail->id_notaris);
            $status = BllNotaris::getStatusByGroup($detail->group_status);
            $class = BllNotaris::getClassByGroup($detail->group_status);
            return $this->render('detail', [
                'detail' => $detail,
                'status' => $status,
                'class' => $class,
                'kantor' => $kantor,
                'summary' => $summary,
                'pengumuman' => $pengumuman
            ]);
        }
    }

    public function actionMap()
    {
        if (Yii::$app->request->isAjax) {
            $id = Yii::$app->request->post('id');
            $data = Notaris::findOne($id);
            if (!empty($data)) {
                return BllNotaris::getTemplateMarker($data);
            } else {
                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['status' => 404];
            }
        }
    }

    public function actionDataNotaris()
    {
        if (Yii::$app->request->isAjax || true) {
            $count_notaris = DalNotaris::getJumlahNotaris();
            $per_kabupaten = DalNotaris::countNotarisAllCity();
            if (!empty($count_notaris) && !empty($per_kabupaten)) {
                return $this->renderAjax('_notaris',[
                    'per_kabupaten' => $per_kabupaten,
                    'count_notaris' => $count_notaris
                ]);
            } else {
                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['status' => 404];
            }
        }
    }

    public function actionDownload($id)
    {
        if (!empty($id)) {
            if ( ($uploads = Uploads::findOne($id)) !== null ) {
                $path = Yii::getAlias('@app') . '/web/uploads/konten';
                $file = $path . '/' . $uploads->file;
                if (file_exists($file) ) {
                    if (!is_file($file) ) {
                        throw new \yii\web\NotFoundHttpException('The file does not exists.');
                    }
                    return Yii::$app->response->sendFile($file);
                } else {
                    throw new NotFoundHttpException('The file does not exists.');
                }
            } else {
                throw new NotFoundHttpException('The file does not exists.');
            }
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionMainMap()
    {
        $markers = DalNotaris::getKoordinateNotaris();
        return $this->renderAjax('_maps', [
            'markers' => $markers
            ]);
    }

    public function actionSlideNotaris()
    {
        $listNotaris = DalNotaris::getDataNotarisAktif();
        return $this->renderAjax('default_index', [
            'listNotaris' => $listNotaris
            ]);
    }
}